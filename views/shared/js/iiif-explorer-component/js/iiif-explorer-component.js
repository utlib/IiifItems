// iiif-explorer-component v1.0.2 https://github.com/viewdir/iiif-explorer-component#readme
(function(f){if(typeof exports==="object"&&typeof module!=="undefined"){module.exports=f()}else if(typeof define==="function"&&define.amd){define([],f)}else{var g;if(typeof window!=="undefined"){g=window}else if(typeof global!=="undefined"){g=global}else if(typeof self!=="undefined"){g=self}else{g=this}g.iiifExplorerComponent = f()}})(function(){var define,module,exports;return (function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
(function (global){

var __extends = (this && this.__extends) || function (d, b) {
    for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p];
    function __() { this.constructor = d; }
    d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
};
var IIIFComponents;
(function (IIIFComponents) {
    var ExplorerComponent = (function (_super) {
        __extends(ExplorerComponent, _super);
        function ExplorerComponent(options) {
            var _this = _super.call(this, options) || this;
            _this._parents = [];
            _this._init();
            _this._resize();
            return _this;
        }
        ExplorerComponent.prototype._init = function () {
            var success = _super.prototype._init.call(this);
            if (!success) {
                console.error("Component failed to initialise");
            }
            var that = this;
            this._$view = $('<div class="explorer-view"></div>');
            this._$element.empty();
            this._$element.append(this._$view);
            $.templates({
                pageTemplate: '<div class="breadcrumbs">\
                                    {^{for parents}}\
                                        {^{breadcrumb/}}\
                                    {{/for}}\
                                </div>\
                                <hr/>\
                                <div class="items">\
                                    {^{for current.members}}\
                                        {^{item/}}\
                                    {{/for}}\
                                </div>',
                breadcrumbTemplate: '<div class="explorer-breadcrumb explorer-item">\
                                        <a class="explorer-breadcrumb-link explorer-link" href="{{>id}}" title="{{>label}}">{{>label}}</a>\
                                    </div>',
                itemTemplate: '{{if getIIIFResourceType().value === "sc:collection"}}\
                                    <div class="explorer-folder {{:~itemClass(id)}}">\
                                        <a class="explorer-folder-link explorer-link" href="{{>id}}" title="{{>label}}">\
                                            {{>label}}\
                                        </a>\
                                {{else}}\
                                    <div class="explorer-resource {{:~itemClass(id)}}">\
                                        <a class="explorer-item-link explorer-link" href="{{>id}}" title="{{>label}}">\
                                            {{>label}}\
                                        </a>\
                                {{/if}}\
                                </div>'
            });
            $.views.helpers({
                itemClass: function (id) {
                    return this._selected && id === this._selected.id
                        ? 'explorer-item selected'
                        : 'explorer-item';
                }.bind(this)
            });
            $.views.tags({
                breadcrumb: {
                    init: function (tagCtx, linkCtx, ctx) {
                        this.data = tagCtx.view.data;
                        this.data.label = Manifesto.TranslationCollection.getValue(this.data.getLabel());
                    },
                    onAfterLink: function () {
                        var self = this;
                        self.contents('.explorer-breadcrumb')
                            .on('click', 'a.explorer-breadcrumb-link', function () {
                            that.gotoBreadcrumb(self.data);
                            return false;
                        });
                    },
                    template: $.templates.breadcrumbTemplate
                },
                item: {
                    init: function (tagCtx, linkCtx, ctx) {
                        this.data = tagCtx.view.data;
                        this.data.label = Manifesto.TranslationCollection.getValue(this.data.getLabel());
                    },
                    onAfterLink: function () {
                        var self = this;
                        self.contents('.explorer-item')
                            .on('click', 'a.explorer-folder-link', function () {
                            that._selected = null;
                            that._switchToFolder(self.data);
                            return false;
                        })
                            .on('click', 'a.explorer-item-link', function () {
                            that._selected = self.data;
                            that._draw();
                            that.fire(ExplorerComponent.Events.EXPLORER_NODE_SELECTED, self.data);
                            return false;
                        });
                    },
                    template: $.templates.itemTemplate
                }
            });
            return success;
        };
        ExplorerComponent.prototype._draw = function () {
            var data = { parents: this._parents, current: this._current, selected: '' };
            if (this._selected) {
                data.selected = this._selected.id;
            }
            this._$view.link($.templates.pageTemplate, data);
        };
        ExplorerComponent.prototype._sortCollectionsFirst = function (a, b) {
            var aType = a.getIIIFResourceType().value;
            var bType = b.getIIIFResourceType().value;
            if (aType === bType) {
                // Alphabetical
                var aLabel = Manifesto.TranslationCollection.getValue(a.getLabel());
                var bLabel = Manifesto.TranslationCollection.getValue(b.getLabel());
                return aLabel < bLabel ? -1 : 1;
            }
            // Collections first
            return bType.indexOf('collection') - aType.indexOf('collection');
        };
        ExplorerComponent.prototype.gotoBreadcrumb = function (node) {
            var index = this._parents.indexOf(node);
            this._current = this._parents[index];
            this._parents = this._parents.slice(0, index + 1);
            this._draw();
        };
        ExplorerComponent.prototype._switchToFolder = function (node) {
            if (!node.isLoaded) {
                node.load().then(this._switchToFolder.bind(this));
            }
            else {
                node.members.sort(this._sortCollectionsFirst);
                this._parents.push(node);
                this._current = node;
                this._draw();
            }
        };
        ExplorerComponent.prototype._followWithin = function (node) {
            var _this = this;
            return new Promise(function (resolve, reject) {
                var url = node.getProperty('within');
                if ($.isArray(url)) {
                    resolve([]);
                }
                var that = _this;
                Manifesto.Utils.loadResource(url)
                    .then(function (parent) {
                    var parentManifest = manifesto.create(parent);
                    if (parentManifest.getProperty('within')) {
                        that._followWithin(parentManifest).then(function (array) {
                            array.push(node);
                            resolve(array);
                        });
                    }
                    else {
                        resolve([parentManifest, node]);
                    }
                })["catch"](reject);
            });
        };
        ExplorerComponent.prototype.set = function () {
            var root = this.options.data.helper.iiifResource;
            if (root.getProperty('within')) {
                var that_1 = this;
                this._followWithin(root).then(function (parents) {
                    that_1._parents = parents;
                    var start = parents.pop();
                    while (start && !start.isCollection()) {
                        start = parents.pop();
                    }
                    that_1._switchToFolder(start);
                });
            }
            if (root.isCollection()) {
                this._switchToFolder(root);
            }
            else {
                this._selected = root;
            }
        };
        ExplorerComponent.prototype.data = function () {
            return {
                helper: null,
                topRangeIndex: 0,
                treeSortType: Manifold.TreeSortType.NONE
            };
        };
        ExplorerComponent.prototype._resize = function () {
        };
        return ExplorerComponent;
    }(_Components.BaseComponent));
    IIIFComponents.ExplorerComponent = ExplorerComponent;
})(IIIFComponents || (IIIFComponents = {}));
(function (IIIFComponents) {
    var ExplorerComponent;
    (function (ExplorerComponent) {
        var Events = (function () {
            function Events() {
            }
            return Events;
        }());
        Events.EXPLORER_NODE_SELECTED = 'explorerNodeSelected';
        ExplorerComponent.Events = Events;
    })(ExplorerComponent = IIIFComponents.ExplorerComponent || (IIIFComponents.ExplorerComponent = {}));
})(IIIFComponents || (IIIFComponents = {}));
(function (g) {
    if (!g.IIIFComponents) {
        g.IIIFComponents = IIIFComponents;
    }
    else {
        g.IIIFComponents.ExplorerComponent = IIIFComponents.ExplorerComponent;
    }
})(global);


}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{}]},{},[1])(1)
});