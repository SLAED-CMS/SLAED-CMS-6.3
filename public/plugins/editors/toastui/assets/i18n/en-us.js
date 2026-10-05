/*!
 * TOAST UI Editor : SLAED English locale
 * @version 3.2.2
 * @license MIT
 */
(function(root) {
    'use strict';

    var editor = root.toastui && root.toastui.Editor;
    if (!editor || typeof editor.setLanguage !== 'function') return;
    editor.setLanguage(['en', 'en-US'], {
        Markdown: 'Markdown',
        WYSIWYG: 'Visual',
        Write: 'Write',
        Preview: 'Preview',
        Headings: 'Headings',
        Paragraph: 'Paragraph',
        Bold: 'Bold',
        Italic: 'Italic',
        Strike: 'Strike',
        Code: 'Inline code',
        Line: 'Line',
        Blockquote: 'Blockquote',
        'Unordered list': 'Unordered list',
        'Ordered list': 'Ordered list',
        Task: 'Task',
        Indent: 'Indent',
        Outdent: 'Outdent',
        'Insert link': 'Insert link',
        'Insert CodeBlock': 'Insert codeBlock',
        'Insert table': 'Insert table',
        'Insert image': 'Insert image',
        Heading: 'Heading',
        'Image URL': 'Image link',
        'Select image file': 'Select file',
        'Choose a file': 'Choose',
        'No file': 'No file',
        Description: 'Description',
        OK: 'Done',
        More: 'More',
        Cancel: 'Cancel',
        File: 'File',
        URL: 'Link',
        'Link text': 'Link text',
        'Add row to up': 'Add row to up',
        'Add row to down': 'Add row to down',
        'Add column to left': 'Add column to left',
        'Add column to right': 'Add column to right',
        'Remove row': 'Remove row',
        'Remove column': 'Remove column',
        'Align column to left': 'Align column to left',
        'Align column to center': 'Align column to center',
        'Align column to right': 'Align column to right',
        'Remove table': 'Remove table',
        'Would you like to paste as table?': 'Would you like to paste as table?',
        'Text color': 'Text color',
        'Auto scroll enabled': 'Auto scroll enabled',
        'Auto scroll disabled': 'Auto scroll disabled',
        'Choose language': 'Choose language'
    });
})(window);
