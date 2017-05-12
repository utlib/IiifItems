(function($){

  $.OmekaAnnotationEditor = function(options) {

    jQuery.extend(this, {
      annotation: null,
      windowId: null,
      config: {
        plugins: '',
        toolbar: '',
        admin: false
      }
    }, options);

    this.init();
  };

  $.OmekaAnnotationEditor.prototype = {
    init: function() {
      var _this = this;
      var annoText = "",
        tags = [];

      this.wasPublic = false;
      this.wasFeatured = false;
      if (!jQuery.isEmptyObject(_this.annotation)) {
        if (jQuery.isArray(_this.annotation.resource)) {
          jQuery.each(_this.annotation.resource, function(index, value) {
            if (value['@type'] === "oa:Tag") {
              tags.push(value.chars);
            } else {
              annoText = value.chars;
            }
          });
        } else {
          annoText = _this.annotation.resource.chars;
        }
        if (_this.annotation._iiifitems_access) {
          _this.wasPublic = !!_this.annotation._iiifitems_access.public;
          _this.wasFeatured = !!_this.annotation._iiifitems_access.featured;
        }
      }
      
      this.editorMarkup = this.editorTemplate({
        content: annoText,
        tags : tags.join(" "),
        windowId : _this.windowId,
        "public" : _this.wasPublic,
        featured : _this.wasFeatured,
        admin: _this.config.admin
      });
    },

    show: function(selector) {
      this.editorContainer = jQuery(selector)
        .prepend(this.editorMarkup);
      tinymce.init({
        selector: selector + ' textarea',
        plugins: this.config.plugins,
        menubar: false,
        statusbar: false,
        toolbar_items_size: 'small',
        toolbar: this.config.toolbar,
        default_link_target:"_blank",
        setup: function(editor) {
          editor.on('init', function(args) {
            tinymce.execCommand('mceFocus', false, args.target.id);
          });
        }
      });
    },

    isDirty: function() {
      return tinymce.activeEditor.isDirty() || this.editorContainer.find('#annotation-editor-public').is(':checked') !== this.wasPublic || this.editorContainer.find('#annotation-editor-featured').is(':checked') !== this.wasFeatured;
    },

    createAnnotation: function() {
      var tagText = this.editorContainer.find('.tags-editor').val(),
        publicBox = this.editorContainer.find('#annotation-editor-public').is(':checked'),
        featuredBox = this.editorContainer.find('#annotation-editor-featured').is(':checked'),
        resourceText = tinymce.activeEditor.getContent(),
        tags = [];
      tagText = $.trimString(tagText);
      if (tagText) {
        tags = tagText.split(/\s+/);
      }

      var motivation = [],
        resource = [],
        on;

      if (tags && tags.length > 0) {
        motivation.push("oa:tagging");
        jQuery.each(tags, function(index, value) {
          resource.push({
            "@type": "oa:Tag",
            "chars": value
          });
        });
      }
      motivation.push("oa:commenting");
      if (motivation.length === 1) {
          motivation = motivation[0];
      }
      
      resource.push({
        "@type": "dctypes:Text",
        "format": "text/html",
        "chars": resourceText
      });
      return {
        "@context": "http://iiif.io/api/presentation/2/context.json",
        "@type": "oa:Annotation",
        "motivation": motivation,
        "resource": resource,
        "_iiifitems_access": {
          "public": publicBox,
          "featured": featuredBox
        }
      };
    },

    updateAnnotation: function(oaAnno) {
      var tagText = this.editorContainer.find('.tags-editor').val(),
        publicBox = this.editorContainer.find('#annotation-editor-public').is(':checked'),
        featuredBox = this.editorContainer.find('#annotation-editor-featured').is(':checked'),
        resourceText = tinymce.activeEditor.getContent(),
        tags = [];
      tagText = $.trimString(tagText);
      if (tagText) {
        tags = tagText.split(/\s+/);
      }

      var motivation = [],
        resource = [];

      //remove all tag-related content in annotation
      if (typeof oaAnno.motivation === 'string') {
          oaAnno.motivation = [oaAnno.motivation];
      }
      oaAnno.motivation = jQuery.grep(oaAnno.motivation, function(value) {
        return value !== "oa:tagging";
      });
      oaAnno.resource = jQuery.grep(oaAnno.resource, function(value) {
        return value["@type"] !== "oa:Tag";
      });
      //re-add tagging if we have them
      if (tags.length > 0) {
        oaAnno.motivation.push("oa:tagging");
        jQuery.each(tags, function(index, value) {
          oaAnno.resource.push({
            "@type": "oa:Tag",
            "chars": value
          });
        });
      }
      jQuery.each(oaAnno.resource, function(index, value) {
        if (value["@type"] === "dctypes:Text") {
          value.chars = resourceText;
        }
      });
      if (oaAnno.motivation.length === 1) {
          oaAnno.motivation = oaAnno.motivation[0];
      }
      //add _iiifitems_access properties
      oaAnno._iiifitems_access = {
        "public": publicBox,
        "featured": featuredBox
      };
    },

    editorTemplate: $.Handlebars.compile([
      '<textarea class="text-editor" placeholder="{{t "comments"}}…">{{#if content}}{{content}}{{/if}}</textarea>',
      '<input id="tags-editor-{{windowId}}" class="tags-editor" placeholder="{{t "addTagsHere"}}…" {{#if tags}}value="{{tags}}"{{/if}}>',
      '{{#if admin}}',
        '<input id="annotation-editor-public" type="checkbox" {{#if public}}checked="checked"{{/if}}><label for="annotation-editor-public"> Public? </label>',
        '<input id="annotation-editor-featured" type="checkbox" {{#if featured}}checked="checked"{{/if}}><label for="annotation-editor-featured"> Featured? </label>',
      '{{/if}}'
    ].join(''))
  };
}(Mirador));
