import EditorJS from 'https://esm.sh/@editorjs/editorjs@2';
import Header from 'https://esm.sh/@editorjs/header@2';
import List from 'https://esm.sh/@editorjs/list@1';
import Quote from 'https://esm.sh/@editorjs/quote@2';
import CodeTool from 'https://esm.sh/@editorjs/code@2';
import Delimiter from 'https://esm.sh/@editorjs/delimiter@1';
import ImageTool from 'https://esm.sh/@editorjs/image@2';
import './carousel-editorjs.js';
const Carousel = window.Carousel;

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
      list: {
        class: List,
        inlineToolbar: true,
      },
      quote: {
        class: Quote,
        inlineToolbar: true,
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

      image: {
        class: ImageTool,
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
        },
      },
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
    'header-1': () => editor.blocks.insert('header', { level: 1 }),
    'header-2': () => editor.blocks.insert('header', { level: 2 }),
    'header-3': () => editor.blocks.insert('header', { level: 3 }),
    'header-4': () => editor.blocks.insert('header', { level: 4 }),
    'list-unordered': () => editor.blocks.insert('list', { style: 'unordered' }),
    'list-ordered': () => editor.blocks.insert('list', { style: 'ordered' }),
    'quote': () => editor.blocks.insert('quote'),
    'code': () => editor.blocks.insert('code'),
    'delimiter': () => editor.blocks.insert('delimiter'),
    'image': () => editor.blocks.insert('image'),
  };
  /*
    toolbar.addEventListener('click', e => {
      const btn = e.target.closest('[data-block]');
      if (btn && blockActions[btn.dataset.block]) {
        editor.isReady.then(() => blockActions[btn.dataset.block]());
      }
    });*/
});
