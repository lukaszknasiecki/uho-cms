import {
	ClassicEditor,
	Autoformat,
	AutoImage,
	Autosave,
	BlockQuote,
	Bold,
	CloudServices,
	Essentials,
	Heading,
	ImageBlock,
	ImageCaption,
	ImageInline,
	ImageInsert,
	//ImageInsertViaUrl,
	ImageResize,
	ImageStyle,
	ImageTextAlternative,
	ImageToolbar,
	ImageUpload,
	Indent,
	IndentBlock,
	Italic,
	Link,
	LinkImage,
	List,
	ListProperties,
	MediaEmbed,
	Paragraph,
	PasteFromOffice,
	SimpleUploadAdapter,
	Table,
	TableCaption,
	TableCellProperties,
	TableColumnResize,
	TableProperties,
	TableToolbar,
	TextTransformation,
	TodoList,
	Underline
} from 'ckeditor5';

/**
 * Create a free account with a trial: https://portal.ckeditor.com/checkout?plan=free
 */
const LICENSE_KEY = 'GPL'; // or <YOUR_LICENSE_KEY>.

const editorConfig = {
	toolbar: {
		viewportTopOffset : 137,
		items: [
			'heading',
			'|',
			'bold',
			'italic',
			'|',
			'link',
			'blockQuote',
			'|',
			'bulletedList',
			'numberedList',
			'outdent',
			'indent',
			'|',
			'insertImage',
			'mediaEmbed'
		],
		shouldNotGroupWhenFull: false
	},
	plugins: [
		Autoformat,
		AutoImage,
		Autosave,
		BlockQuote,
		Bold,
		CloudServices,
		Essentials,
		Heading,
		ImageBlock,
		ImageCaption,
		ImageInline,
		ImageInsert,
		//ImageInsertViaUrl,
		ImageResize,
		ImageStyle,
		ImageTextAlternative,
		ImageToolbar,
		ImageUpload,
		Indent,
		IndentBlock,
		Italic,
		Link,
		LinkImage,
		List,
		ListProperties,
		MediaEmbed,
		Paragraph,
		PasteFromOffice,
		SimpleUploadAdapter,
		Table,
		TableCaption,
		TableCellProperties,
		TableColumnResize,
		TableProperties,
		TableToolbar,
		TextTransformation,
		TodoList,
		Underline
	],
	heading: {
		options: [
			{
				model: 'paragraph',
				title: 'Paragraph',
				class: 'ck-heading_paragraph'
			},
			{
				model: 'heading2',
				view: 'h2',
				title: 'Heading 2',
				class: 'ck-heading_heading2'
			},
			{
				model: 'heading3',
				view: 'h3',
				title: 'Heading 3',
				class: 'ck-heading_heading3'
			}
		]
	},
	image: {
		toolbar: [
		//	'toggleImageCaption',
			'imageTextAlternative'
		//	'|',
		//	'imageStyle:inline',
		//	'imageStyle:wrapText',
		//	'imageStyle:breakText',
		//	'|',
		//	'resizeImage'
		]
	},
	
	licenseKey: LICENSE_KEY,
	link: {
		addTargetToExternalLinks: true,
		defaultProtocol: 'https://',
		decorators: {
			toggleDownloadable: {
				mode: 'manual',
				label: 'Downloadable',
				attributes: {
					download: 'file'
				}
			}
		}
	},

	simpleUpload:{
		
		uploadUrl: '/serdelia/api/uploader?type=binary',

		// Enable the XMLHttpRequest.withCredentials property.
		//withCredentials: true,
		// Headers sent along with the XMLHttpRequest to the upload server.
		//headers: {
		//	'X-CSRF-TOKEN': 'CSRF-Token',
		//	Authorization: 'Bearer <JSON Web Token>'
		//}
	},

	list: {
		properties: {
			styles: true,
			startIndex: true,
			reversed: true
		}
	},
	placeholder: 'Type or paste your content here!',
	table: {
		contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']
	}
};




$(document).ready( function()
{

	var Editors = $('.ckeditor5');

	for(var k=0; k<Editors.length; k++)
	{
		ClassicEditor.create(Editors[k], editorConfig)
		.then( editor => {	
			
			CKEditors5['e_text']=editor;
			editor.model.document.on( 'change:data', ( evt, data ) => {
				Apps.Edit.changeField();
			} );
		} )
		.catch( error => {
			console.error( error );
		} );
		
	}

	
    
});

