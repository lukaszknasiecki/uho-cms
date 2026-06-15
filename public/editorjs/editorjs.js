import EditorJS from 'https://esm.sh/@editorjs/editorjs@2';
import Header from 'https://esm.sh/@editorjs/header@2';
import List from 'https://esm.sh/@editorjs/list@1';
import Quote from 'https://esm.sh/@editorjs/quote@2';
import CodeTool from 'https://esm.sh/@editorjs/code@2';
import Delimiter from 'https://esm.sh/@editorjs/delimiter@1';
import ImageTool from 'https://esm.sh/@editorjs/image@2';
import RawTool from 'https://esm.sh/@editorjs/raw@2';
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
  const editor = new EditorJS({
    holder: editorEl,
    data: initialData || undefined,

    tools: {
      header: {
        class: Header,
        config: { levels: [3, 4], defaultLevel: 3 },
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

      raw: {
        class: RawTool,
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
