import EditorJS from 'https://esm.sh/@editorjs/editorjs@2';
import Header from 'https://esm.sh/@editorjs/header@2';
import List from 'https://esm.sh/@editorjs/list@1';
import Quote from 'https://esm.sh/@editorjs/quote@2';
import CodeTool from 'https://esm.sh/@editorjs/code@2';
import Delimiter from 'https://esm.sh/@editorjs/delimiter@1';
import ImageTool from 'https://esm.sh/@editorjs/image@2';
import RawTool from 'https://esm.sh/@editorjs/raw@2';

class RawToolFixed extends RawTool {
  render() {
    const wrapper = super.render();
    const textarea = wrapper.querySelector('textarea');
    if (textarea) {
      textarea.addEventListener('keydown', (e) => e.stopPropagation());
    }
    return wrapper;
  }
}

import './carousel-editorjs.js';
const Carousel = window.Carousel;

class LeadTool {
  static get toolbox() {
    return {
      title: 'Lead',
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10"/></svg>',
    };
  }

  static get sanitize() {
    return { text: { br: true } };
  }

  static get conversionConfig() {
    return {
      export: (data) => data.text,
      import: (content) => ({ text: content }),
    };
  }

  constructor({ data }) {
    this.data = { text: data.text || '' };
    this._element = null;
  }

  render() {
    this._element = document.createElement('p');
    this._element.classList.add('ce-lead');
    this._element.contentEditable = 'true';
    this._element.innerHTML = this.data.text;
    return this._element;
  }

  save(el) {
    return { text: el.innerHTML };
  }
}


class CustomDataTool {
  static get toolbox() {
    return {
      title: 'Custom Data',
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>',
    };
  }

  constructor({ data, block }) {
    this.data = { key: data.key || '', value: data.value || '' };
    this._wrapper = null;
    this._block = block;
  }

  render() {
    this._wrapper = document.createElement('div');
    this._wrapper.classList.add('ce-custom-data');
    this._wrapper.style.cssText = 'display:flex;gap:8px;padding:10px 12px;margin:12px 0;background:#f5f5f5;border-radius:6px';

    const keyInput = document.createElement('select');
    keyInput.style.cssText = 'flex:0.5;padding:6px 8px;border:1px solid #e0e0e0;border-radius:4px;font-size:14px';
    ['Clips', 'Accordions'].forEach(opt => {
      const option = document.createElement('option');
      option.value = opt;
      option.textContent = opt;
      option.selected = this.data.key === opt;
      keyInput.appendChild(option);
    });
    keyInput.addEventListener('keydown', (e) => e.stopPropagation());
    keyInput.addEventListener('change', () => this._block.dispatchChange());

    const valueInput = document.createElement('input');
    valueInput.type = 'text';
    valueInput.placeholder = 'Value';
    valueInput.value = this.data.value;
    valueInput.style.cssText = 'flex:2;padding:6px 8px;border:1px solid #e0e0e0;border-radius:4px;font-size:14px';
    valueInput.addEventListener('keydown', (e) => e.stopPropagation());
    valueInput.addEventListener('input', () => this._block.dispatchChange());

    this._wrapper.appendChild(keyInput);
    this._wrapper.appendChild(valueInput);
    return this._wrapper;
  }

  save() {
    const keyInput = this._wrapper.querySelector('select');
    const valueInput = this._wrapper.querySelector('input');
    return { key: keyInput.value, value: valueInput.value.trim() };
  }

  validate(data) {
    return data.key.trim() !== '' || data.value.trim() !== '';
  }
}


document.querySelectorAll('.editorjs-editor-wrapper').forEach(wrapper => {
  const editorEl = wrapper.querySelector('.editorjs-editor');
  const toolbar = wrapper.querySelector('.editorjs-toolbar');
  const destination = wrapper.dataset.destination
    ? document.getElementById(wrapper.dataset.destination)
    : null;

  let initialData;
  if (destination?.value.trim()) {
    try {
      initialData = JSON.parse(destination.value);
    } catch {
      initialData = undefined;
    }
  }

  /*
    disabled:
          code: CodeTool,
          delimiter: Delimiter,
  */
  const editorStartTime = Date.now();

  const editor = new EditorJS({
    holder: editorEl,
    data: initialData || undefined,

    tools: {
      header: {
        class: Header,
        config: { levels: [3, 4], defaultLevel: 3 },
      },

      lead: {
        class: LeadTool,
        inlineToolbar: true,
      },
      list: {
        class: List,
        inlineToolbar: true,
      },
      quote: {
        class: Quote,
        inlineToolbar: true,
      },
      customData: {
        class: CustomDataTool,
      },
      image: {
        class: ImageTool,
        config: {
          endpoints: {
            byFile: document.body.dataset.basePath + "/api/uploader?type=editorjs", // upload file to server
            //byUrl: '/api/fetchImage',   // optional: fetch by URL
          }
        },
      },
      carousel: {
        class: Carousel,
        toolbox: {
          title: 'Carousel',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="13" height="14" rx="1"/><path d="M17 8l4 4-4 4"/><path d="M7 8l-4 4 4 4"/></svg>',
        },
        config: {
          uploader: {
            uploadByFile(file) {
              const formData = new FormData();
              formData.append("upload", file);

              return fetch(document.body.dataset.basePath + "/api/uploader?type=carousel_editorjs", {
                method: "POST",
                body: formData
              })
                .then(response => response.json())
                .then(data => {
                  return data;
                });
            }
          }
        }
      },

      raw: {
        class: RawToolFixed,
        config: { placeholder: 'Enter custom data' },
      },


      /* base64 image - discarded because of size limitations, but left here for reference
      config: {
        uploader: {
          uploadByUrl(url) {
            return Promise.resolve({ success: 1, file: { url } });
          },
          uploadByFile(file) {
            return new Promise(resolve => {
              const reader = new FileReader();
              reader.onload = e => resolve({ success: 1, file: { url: e.target.result } });
              reader.readAsDataURL(file);
            });
          },
        },
      },*/

    },
    placeholder: 'Start writing…',
    onChange: async () => {
      if (Date.now() - editorStartTime < 3000) return;
      if (destination) {
        const outputData = await editor.save();
        destination.value = JSON.stringify(outputData);
      }
      if (window.Apps?.Edit?.changeField) {
        window.Apps.Edit.changeField();
      }
    },
  });

  const blockActions = {
    'paragraph': () => editor.blocks.insert('paragraph'),
    'lead': () => editor.blocks.insert('lead'),
    'header-1': () => editor.blocks.insert('header', { level: 1 }),
    'header-2': () => editor.blocks.insert('header', { level: 2 }),
    'header-3': () => editor.blocks.insert('header', { level: 3 }),
    'header-4': () => editor.blocks.insert('header', { level: 4 }),
    'image': () => editor.blocks.insert('image'),
    'lead': () => editor.blocks.insert('lead'),
    'list-unordered': () => editor.blocks.insert('list', { style: 'unordered' }),
    'list-ordered': () => editor.blocks.insert('list', { style: 'ordered' }),
    'quote': () => editor.blocks.insert('quote'),
    'code': () => editor.blocks.insert('code'),
    'delimiter': () => editor.blocks.insert('delimiter')
  };
  /*
    toolbar.addEventListener('click', e => {
      const btn = e.target.closest('[data-block]');
      if (btn && blockActions[btn.dataset.block]) {
        editor.isReady.then(() => blockActions[btn.dataset.block]());
      }
    });*/
});
