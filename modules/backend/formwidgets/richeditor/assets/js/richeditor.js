/*
 * Rich text editor form field control (WYSIWYG)
 *
 * Data attributes:
 * - data-control="richeditor" - enables the rich editor plugin
 *
 * JavaScript API:
 * $('textarea').richEditor()
 *
 * Dependencies:
 * - Froala Editor (froala_editor.js)
 */
+(function($) {
    'use strict';
    var Base = $.oc.foundation.base,
        BaseProto = Base.prototype;

    // RICHEDITOR CLASS DEFINITION
    // ============================

    var RichEditor = function(element, options) {
        this.options = options;
        this.$el = $(element);
        this.$textarea = this.$el.find('textarea[data-richeditor-textarea]:first');
        this.$form = this.$el.closest('form');
        this.editor = null;

        $.oc.foundation.controlUtils.markDisposable(element);

        Base.call(this);

        this.init();
    };

    RichEditor.prototype = Object.create(BaseProto);
    RichEditor.prototype.constructor = RichEditor;

    RichEditor.DEFAULTS = {
        linksHandler: null,
        stylesheet: null,
        fullpage: false,
        editorLang: 'en',
        editorOptions: null,
        useMediaManager: false,
        toolbarButtons: null,
        allowEmptyTags: null,
        allowTags: null,
        allowAttrs: null,
        noWrapTags: null,
        removeTags: null,
        lineBreakerTags: null,
        imageStyles: null,
        linkStyles: null,
        paragraphStyles: null,
        inlineStyles: null,
        paragraphFormat: null,
        tableStyles: null,
        tableCellStyles: null,
        useLineBreaks: null,
        aceVendorPath: '/',
        readOnly: false
    };

    RichEditor.prototype.init = function() {
        this.$el.one('dispose-control', this.proxy(this.dispose));

        // Textarea must have an identifier
        if (!this.$textarea.attr('id')) {
            this.$textarea.attr('id', 'element-' + Math.random().toString(36).substring(7));
        }

        if (this.options.editorOptions.constructor !== {}.constructor) {
            this.options.editorOptions = {};
        }

        if (!this.options.legacyMode) {
            this.initVueConnector();
            return;
        }

        // Initialize Froala editor
        this.initFroala();
    };

    RichEditor.prototype.initFroala = function() {
        var froalaOptions = {
            ...this.options.editorOptions,
            editorClass: 'control-richeditor',
            language: this.options.editorLang,
            fullPage: this.options.fullpage,
            aceEditorVendorPath: this.options.aceVendorPath,
            toolbarSticky: false
        };

        if (this.options.useLineBreaks) {
            froalaOptions.enter = $.FroalaEditor.ENTER_BR;
        }

        if (this.options.toolbarButtons) {
            froalaOptions.toolbarButtons = this.options.toolbarButtons.split(',');
        }
        else {
            froalaOptions.toolbarButtons = oc.richEditorButtons;
        }

        froalaOptions.imageStyles = this.options.imageStyles
            ? this.options.imageStyles
            : {
                'oc-img-rounded': 'Rounded',
                'oc-img-bordered': 'Bordered'
              };

        froalaOptions.linkStyles = this.options.linkStyles
            ? this.options.linkStyles
            : {
                'oc-link-green': 'Green',
                'oc-link-strong': 'Thick'
              };

        froalaOptions.paragraphFormat = this.options.paragraphFormat
            ? this.options.paragraphFormat
            : {
                'N': 'Normal',
                'H1': 'Heading 1',
                'H2': 'Heading 2',
                'H3': 'Heading 3',
                'H4': 'Heading 4',
                'PRE': 'Code'
            }

        froalaOptions.paragraphStyles = this.options.paragraphStyles
            ? this.options.paragraphStyles
            : {
                'oc-text-gray': 'Gray',
                'oc-text-bordered': 'Bordered',
                'oc-text-spaced': 'Spaced',
                'oc-text-uppercase': 'Uppercase'
              };

        froalaOptions.inlineStyles = this.options.inlineStyles
            ? this.options.inlineStyles
            : {
                'oc-class-code': 'Code',
                'oc-class-highlighted': 'Highlighted',
                'oc-class-transparency': 'Transparent'
              };

        froalaOptions.tableStyles = this.options.tableStyles
            ? this.options.tableStyles
            : {
                'oc-dashed-borders': 'Dashed Borders',
                'oc-alternate-rows': 'Alternate Rows'
              };

        froalaOptions.tableCellStyles = this.options.tableCellStyles
            ? this.options.tableCellStyles
            : {
                'oc-cell-highlighted': 'Highlighted',
                'oc-cell-thick-border': 'Thick'
              };

        froalaOptions.toolbarButtonsMD = froalaOptions.toolbarButtons;
        froalaOptions.toolbarButtonsSM = froalaOptions.toolbarButtons;
        froalaOptions.toolbarButtonsXS = froalaOptions.toolbarButtons;

        if (this.options.allowEmptyTags) {
            froalaOptions.htmlAllowedEmptyTags = this.options.allowEmptyTags.split(/[\s,]+/);
        }

        if (this.options.allowTags) {
            froalaOptions.htmlAllowedTags = this.options.allowTags.split(/[\s,]+/);
        }

        if (this.options.allowAttrs) {
            froalaOptions.htmlAllowedAttrs = $.extend($.FroalaEditor.DEFAULTS.htmlAllowedAttrs, this.options.allowAttrs.split(/[\s,]+/));
        }

        if (this.options.noWrapTags) {
            froalaOptions.htmlDoNotWrapTags = this.options.noWrapTags.split(/[\s,]+/);
        }

        if (this.options.removeTags) {
            froalaOptions.htmlRemoveTags = this.options.removeTags.split(/[\s,]+/);
        }

        froalaOptions.lineBreakerTags = this.options.lineBreakerTags
            ? this.options.lineBreakerTags.split(/[\s,]+/)
            : ['figure, table, hr, iframe, form, dl'];

        froalaOptions.shortcutsEnabled = ['show', 'bold', 'italic', 'underline', 'indent', 'outdent', 'undo', 'redo'];

        // File upload
        froalaOptions.imageUploadURL = froalaOptions.fileUploadURL = window.location;
        froalaOptions.imageUploadParam = froalaOptions.fileUploadParam = 'file_data';
        froalaOptions.imageUploadParams = froalaOptions.fileUploadParams = {
            X_OCTOBER_MEDIA_MANAGER_QUICK_UPLOAD: 1,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        var placeholder = this.$textarea.attr('placeholder');
        froalaOptions.placeholderText = placeholder ? placeholder : '';

        froalaOptions.height = this.$el.hasClass('stretch') ? Infinity : $('.height-indicator', this.$el).height();

        if (!this.options.useMediaManager) {
            delete $.FroalaEditor.PLUGINS.mediaManager;
        }

        this.$textarea.on('froalaEditor.initialized', this.proxy(this.build));
        this.$textarea.on('froalaEditor.contentChanged', this.proxy(this.onChange));
        this.$textarea.on('froalaEditor.html.get', this.proxy(this.onSyncContent));
        this.$textarea.on('froalaEditor.html.set', this.proxy(this.onSetContent));
        this.$form.on('oc.beforeRequest', this.proxy(this.onFormBeforeRequest));

        this.$textarea.froalaEditor(froalaOptions);

        this.editor = this.$textarea.data('froala.editor');

        if (this.options.readOnly) {
            this.editor.edit.off();
        }

        this.$el.on('keydown', '.fr-view figure', this.proxy(this.onFigureKeydown));
    };

    RichEditor.prototype.dispose = function() {
        this.unregisterHandlers();

        if (this.vueWidget) {
            this.vueWidget.remove();
            this.vueWidget = null;
        }
        else {
            this.$textarea.froalaEditor('destroy');
        }

        this.$el.removeData('oc.richEditor');

        this.options = null;
        this.$el = null;
        this.$textarea = null;
        this.$form = null;
        this.editor = null;

        BaseProto.dispose.call(this);
    };

    RichEditor.prototype.unregisterHandlers = function() {
        this.$el.off('keydown', '.fr-view figure', this.proxy(this.onFigureKeydown));

        this.$textarea.off('froalaEditor.initialized', this.proxy(this.build));
        this.$textarea.off('froalaEditor.contentChanged', this.proxy(this.onChange));
        this.$textarea.off('froalaEditor.html.get', this.proxy(this.onSyncContent));
        this.$textarea.off('froalaEditor.html.set', this.proxy(this.onSetContent));
        this.$form.off('oc.beforeRequest', this.proxy(this.onFormBeforeRequest));

        $(window).off('resize', this.proxy(this.updateLayout));
        $(window).off('oc.updateUi', this.proxy(this.updateLayout));
        this.$el.off('dispose-control', this.proxy(this.dispose));
    };

    RichEditor.prototype.build = function(event, editor) {
        this.updateLayout();

        $(window).on('resize', this.proxy(this.updateLayout));
        $(window).on('oc.updateUi', this.proxy(this.updateLayout));

        // Bind the keydown listener here to ensure it gets handled before the Froala handlers
        editor.events.on('keydown', this.proxy(this.onKeydown), true);

        this.$textarea.trigger('init.oc.richeditor', [this]);
    };

    RichEditor.prototype.isCodeViewActive = function() {
        return this.editor && this.editor.codeView && this.editor.codeView.isActive();
    };

    RichEditor.prototype.getElement = function() {
        return this.$el;
    };

    RichEditor.prototype.getEditor = function() {
        if (!this.options.legacyMode) {
            return this.vueWidget.getEditor();
        }

        return this.editor;
    };

    RichEditor.prototype.getTextarea = function() {
        return this.$textarea;
    };

    RichEditor.prototype.getContent = function() {
        return this.getEditor().html.get();
    };

    RichEditor.prototype.setContent = function (html) {
        if (!this.options.legacyMode) {
            this.vueWidget.setContent(html);
            return;
        }

        this.getEditor().html.set(html);
    };

    RichEditor.prototype.syncContent = function() {
        this.editor.events.trigger('contentChanged');
    };

    RichEditor.prototype.updateLayout = function() {
        var $editor = $('.fr-wrapper', this.$el),
            $codeEditor = $('.fr-code', this.$el),
            $toolbar = $('.fr-toolbar', this.$el),
            $box = $('.fr-box', this.$el);

        if (!$editor.length) {
            return;
        }

        if (this.$el.hasClass('stretch') && !$box.hasClass('fr-fullscreen')) {
            var height = $toolbar.outerHeight(true);
            $editor.css('top', height + 1);
            $codeEditor.css('top', height);
        }
        else {
            $editor.css('top', '');
            $codeEditor.css('top', '');
        }
    };

    RichEditor.prototype.insertHtml = function(html) {
        this.getEditor().html.insert(html);
        this.getEditor().selection.restore();
    };

    RichEditor.prototype.insertElement = function($el) {
        this.insertHtml($('<div />').append($el.clone()).remove().html());
    };

    /*
     * Inserts non-editable block (used for snippets, audio and video)
     */
    RichEditor.prototype.insertUiBlock = function($node) {
        this.$textarea.froalaEditor('figures.insert', $node);
    };

    RichEditor.prototype.insertVideo = function(url, title) {
        this.$textarea.froalaEditor('figures.insertVideo', url, title);
    };

    RichEditor.prototype.insertAudio = function(url, title) {
        this.$textarea.froalaEditor('figures.insertAudio', url, title);
    };

    // EVENT HANDLERS
    // ============================

    RichEditor.prototype.onSetContent = function(ev, editor) {
        this.$textarea.trigger('setContent.oc.richeditor', [this]);
    };

    RichEditor.prototype.onSyncContent = function(ev, editor, html) {
        // Beautify HTML.
        if (editor.codeBeautifier) {
            html = editor.codeBeautifier.run(html, editor.opts.codeBeautifierOptions);
        }

        var container = {
            html: html
        };

        this.$textarea.trigger('syncContent.oc.richeditor', [this, container]);

        return container.html;
    };

    RichEditor.prototype.onFocus = function() {
        this.$el.addClass('editor-focus');
    };

    RichEditor.prototype.onBlur = function() {
        this.$el.removeClass('editor-focus');
    };

    RichEditor.prototype.onFigureKeydown = function(ev) {
        this.$textarea.trigger('figureKeydown.oc.richeditor', [ev, this]);
    };

    RichEditor.prototype.onKeydown = function(ev, editor, keyEv) {
        this.$textarea.trigger('keydown.oc.richeditor', [keyEv, this]);

        if (ev.isDefaultPrevented()) {
            return false;
        }
    };

    RichEditor.prototype.onChange = function(ev) {
        this.$form.trigger('change');
    };

    /*
     * Instantly synchronizes HTML content.
     * The onSyncContent() method (above) is involved into this call,
     * so the resulting HTML is (optionally) beautified.
     */
    RichEditor.prototype.onFormBeforeRequest = function(ev) {
        if (!this.editor) {
            return;
        }

        if (this.isCodeViewActive()) {
            this.editor.html.set(this.editor.codeView.get());
        }

        this.$textarea.val(this.editor.html.get());
    };

    //
    // Vue mode
    //

    RichEditor.prototype.initVueConnector = function() {
        var Widget = oc.Modules.import('backend.vuecomponents.richeditordocumentconnector.formwidget'),
            that = this;

        this.vueWidget = new Widget(this.$textarea.get(0), this.options, function() {
            that.$form.trigger('change');
        });
    };

    // RICHEDITOR PLUGIN DEFINITION
    // ============================

    var old = $.fn.richEditor;

    $.fn.richEditor = function(option) {
        var args = Array.prototype.slice.call(arguments, 1),
            result;
        this.each(function() {
            var $this = $(this);
            var data = $this.data('oc.richEditor');
            var options = $.extend({}, RichEditor.DEFAULTS, $this.data(), typeof option == 'object' && option);
            if (!data) $this.data('oc.richEditor', (data = new RichEditor(this, options)));
            if (typeof option == 'string') result = data[option].apply(data, args);
            if (typeof result != 'undefined') return false;
        });

        return result ? result : this;
    };

    $.fn.richEditor.Constructor = RichEditor;

    // RICHEDITOR NO CONFLICT
    // =================

    $.fn.richEditor.noConflict = function() {
        $.fn.richEditor = old;
        return this;
    };

    // RICHEDITOR DATA-API
    // ===============
    $(document).render(function() {
        $('[data-control="richeditor"]:not([data-richeditor-vue])').richEditor();
    });

    // BUTTON DEFINITIONS
    // =================

    oc.richEditorRegisterButton = $.FE.RegisterCommand;

    oc.richEditorButtons = [
        'paragraphFormat',
        'align',
        'bold',
        'italic',
        'underline',
        '|',
        'formatOL',
        'formatUL',
        '|',
        'insertSnippet',
        'insertTable',
        'insertPageLink',
        'insertImage',
        'insertHR',
        'html'
    ];

    if ($.oc === undefined) {
        $.oc = {};
    }

    $.oc.richEditorButtons = oc.richEditorButtons;

})(window.jQuery);
