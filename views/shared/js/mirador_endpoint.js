/** IiifItems endpoint **/
(function($) {
    $.IiifItemsAnnotations = function(options) {
        jQuery.extend(this, {
            token:     null,
            prefix:    null,
            dfd:       null,
            admin:     false,
            userId:    null,
            annotationsList: [],        
            windowID: null,
            eventEmitter: null
        }, options);

        this.init();
    };

    $.IiifItemsAnnotations.prototype = {
        init: function() { 		  
        },
        
        set: function(prop, value, options) {
            if (options) {
                this[options.parent][prop] = value;
            } else {
                this[prop] = value;
            }
        },
        
        search: function(options, successCallback, errorCallback) {
            var _this = this;
            this.annotationsList = []; //clear out current list

            jQuery.ajax({
                url: this.prefix + '/index',
                type: 'GET',
                dataType: 'json',
                headers: {},
                data: {
                    uri: options.uri
                },
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    jQuery.each(data, function(index, value) {
                        value.endpoint = _this;
                        _this.annotationsList.push(value);
                    });
                    if (typeof successCallback === "function") {
                        successCallback(_this.annotationsList);
                    } else {
                        _this.dfd.resolve(true);
                    }
                },
                error: function() {
                    if (typeof errorCallback === "function") {
                        errorCallback();
                    }
                }
            });
        },
        
        deleteAnnotation: function(annotationID, successCallback, errorCallback) {
            var _this = this;
            jQuery.ajax({
                url: this.prefix + "/delete",
                type: 'DELETE',
                dataType: 'json',
                headers: {},
                data: JSON.stringify({
                    'id': annotationID,
                }),
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    if (typeof errorCallback === "function") {
                        successCallback();
                    }
                },
                error: function() {
                    if (typeof errorCallback === "function") {
                        errorCallback();
                    }
                }
            });
        },

        update: function(oaAnnotation, successCallback, errorCallback) {
            var _this = this;
            delete oaAnnotation.endpoint;
            jQuery.ajax({
                url: this.prefix + '/update',
                type: 'PUT',
                dataType: 'json',
                headers: {},
                data: JSON.stringify(oaAnnotation),
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    data.endpoint = _this;
                    if (typeof successCallback === "function") {
                        successCallback(data);
                    }
                },
                error: function() {
                    if (typeof errorCallback === "function") {
                        errorCallback();
                    }
                }  
            });
        },

        //takes OA Annotation, gets Endpoint Annotation, and saves
        //if successful, MUST return the OA rendering of the annotation
        create: function(oaAnnotation, successCallback, errorCallback) {
            var _this = this;
            
            // Pull the bounds for the SVG selector
            // Mirador 2.2-: Pull from oaAnnotation.on.selector.value and add _dims
            if (typeof oaAnnotation.on === 'object' && oaAnnotation.on.hasOwnProperty('selector') && oaAnnotation.on.selector.hasOwnProperty('value')) {
                // Pull the bounds for the SVG selector
                var div = document.createElement('div');
                div.setAttribute('style', 'position:absolute;right:-10000px;bottom:-10000px;');
                document.body.appendChild(div);
                div.innerHTML = oaAnnotation.on.selector.value;
                var svgElement = div.firstChild;
                var bbox = svgElement.getBBox();
                oaAnnotation._dims = [Math.round(bbox.x), Math.round(bbox.y), Math.round(bbox.width), Math.round(bbox.height)];
                document.body.removeChild(document.body.lastChild);
            }

            jQuery.ajax({
                url: this.prefix,
                type: "POST",
                dataType: "json",
                headers: {},
                data: JSON.stringify(oaAnnotation),
                contentType: "application/json; charset=utf-8",
                success: function(data) {
                    if (typeof successCallback === "function") {
                        successCallback(data);
                    }
                    data.endpoint = _this;
                },
                error: function() {
                    if (typeof errorCallback === "function") {
                        errorCallback();
                    }
                }  
            });
        },

        getAnnotationList: function(key) {
            var data = localStorage.getItem(key);
            if (data) {
                return JSON.parse(data);
            } else {
                return [];
            }
        },

        userAuthorize: function(action, annotation) {
            return this.admin || (annotation._iiifitems_access.owner == this.userId);
        }
    };
}(Mirador));

