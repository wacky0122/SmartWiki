/**
 * Created by wacky on 2017/8/3.
 */
;(function($) {
    window.requestFileSystem = window.requestFileSystem || window.webkitRequestFileSystem;
    console.log("htmlConverter.js...........");
    function Converter(opts) {
        this.options = $.extend( true, {}, Converter.options, opts );
        this._init( this.options );
    }

    Converter.options = {};
    $.extend(Converter.prototype, {
        bookId: null,//图书ID
        bookName: null,//图书名称
        description: null, //图书描述
        sections: [],//图书章节
        timeOut: 100,//超时时间
        timeCount: 20,//超时次数
        state: "pending",//转换状态
        loadState: "loaded",//加载状态
        iframeId: "htmlConverterFrame",//加载页面的frame节点ID
        include: ".calibreEbookContent",//页面截取内容选择器
        exclude: [".calibreEbNavTop"],//页面排除内容选择器

        _init: function( opts ) {
            var self = this;
            for(var name in opts) {
                self[name] = opts[name];
            }
            self.state = "ready";
        },

        _getFrameNode: function() {
            var self = this, $iframe = $("#" + self.iframeId);
            if (($iframe.length == 0) && self.iframeId) {
                $iframe = $("<iframe id=\"" + self.iframeId + "\" style=\"display:none;\"><\/iframe>");
            }
            return $iframe;
        },

        _loadHtmlFile: function(fileUrl) {
            var self = this,
                $iframe = self._getFrameNode().remove();
            self.loadState = "loading";
            $iframe.load(function() {
                self.loadState = "loaded";
            });
            /*
            $iframe.attr("src", (fileUrl.indexOf("file:///") == "0" ?
                fileUrl : "file:///" + filePath)).appendTo("body");
            */
            $iframe.attr("src", fileUrl).appendTo("body");

        },

        _loadHtmlContent: function(content) {
            var self = this,
                $iframe = self._getFrameNode().remove();
            self.loadState = "loading";
            $iframe.load(function() {
                $(this).contents().find("body").append(content);
                self.loadState = "loaded";
            });
            $iframe.attr("src", "about:blank").appendTo("body");
        },

        _isLoadCompleted: function() {
            return this.loadState === "loaded";
        },

        _isCompleted: function() {
            return this.state === "completed";
        },

        _getContentNodes: function() {
            var self = this,
                $iframe = self._getFrameNode(),
                $content = $iframe.contents().find("body");
            self.include && ($content = $content.find(self.include));
            if (self.exclude && (self.exclude.length > 0)) {
                $.each(self.exclude, function(i, item) {
                    $content.find(item).remove();
                });
            }
            return $content;
        },

        _getBookDetail: function() {
            var self = this,
                $iframe = self._getFrameNode();
            var bookdetail = {
                    title: $iframe.contents().find("meta[name='DC.title']"),
                    date: $iframe.contents().find("meta[name='DC.date']"),
                    author: $iframe.contents().find("meta[name='DC.creator']"),
                    publisher: $iframe.contents().find("meta[name='DC.publisher']"),
                    description: $iframe.contents().find("meta[name='DC.description']")
                };
            self.bookName = bookdetail.title;
            self.description = bookdetail.description + "作者：" + bookdetail.author
                + ";出版：" + bookdetail.publisher + ";出版日期：" + bookdetail.date;
            return bookdetail;
        },

        //Html内容转markdown
        _htmlToMarkdown: function(content) {
            return toMarkdown(content, {gfm: true});
        },

        _parseIndexFile: function() {
            var self = this,
                $content = self._getContentNodes();
            var parseIndexFunc = function(parent) {
                var selfFunc = arguments.callee,
                    parentIndex = parent;
                if ($(this).is("li")) {
                    var title = $(item).text(),
                        href = $(item).attr("href"),
                        index = href.lastIndexOf("#");
                    if (index > -1) {
                        node = href.substr(index + 1);
                        href = href.substr(0, index);
                    }
                    parentIndex = self.sections.length;
                    self.sections[parentIndex] = {
                        title: title,
                        href: href,
                        node: node,
                        parent: parent
                    }
                }
                $(this).children().each(function(i, item) {
                    selfFunc.call($(item), null);
                });
            }

            self.sections = [];
            self._getBookDetail();
            $content.children().each(function(i, item) {
               if ($(item).is("ul")) {
                   parseIndexFunc.call($(item), -1);
               }
            });
            return self.sections;
        },

        _parseSections: function() {
            var self = this, secStates = [],
                parseState = "completed";
            var parseSecFunc = function(curSec, preSec, nextSec) {
                parseState = "execting";
                var iCount = 0, curNode = curSec.node,
                    preNode = null, nextNode = null;
                preSec && (preSec.href == curSec.href) && (preNode = preSec.node);
                nextSec && (nextSec.href == curSec.href) && (nextNode = nextSec.node);
                (preSec && (preSec.href != curSec.href)) ?
                    self._loadHtmlFile(curSec.href) : (self.loadState = "loaded");

                setTimeout(function() {
                    iCount = iCount + 1;
                    if (self._isLoadCompleted()) {
                        var sec = self._parseHtmlFile(curNode, nextNode);
                        for (var name in sec) {
                            curSec[name] = sec[name];
                        }
                        parseState = "completed";
                        secStates[secStates.length] = "completed";
                    } else if (iCount < self.timeCount) {
                        setTimeout(arguments.callee, self.timeout);
                    } else {
                        console.log("加载" + curSec.href + "文件失败。。。。");
                    }
                }, 5);
            }, executeParse = function(curSec, preSec, nextSec) {
                var iCount = 0;
                setTimeout(function() {
                    iCount = iCount + 1;
                    if (parseState === "completed") {
                        parseSecFunc(curSec, preSec, nextSec)
                    } else if (iCount < self.timeCount) {
                        setTimeout(arguments.callee, self.timeout);
                    } else {
                        console.log("解析" + curSec.href + "文件失败。。。。");
                    }
                }, 5);
            };

            for (var i = 0, iSize = self.sections.length; i < iSize; i++) {
                if (self.sections[i].href) {
                    executeParse(self.sections[i], (i == 0 ? null : self.sections[i-1]),
                        (i + 1 < iSize ? self.sections[i + 1] : null));
                }
            }

            var waitCount = 0;
            setTimeout(function() {
                if (secStates.length < self.sections.length) {
                    self.state = "completed";
                } else if (waitCount < self.timeCount * 2) {
                    setTimeout(arguments.callee, self.timeout);
                } else {
                    console.log("解析sections文件失败。。。。");
                }
            }, self.timeout)
        },

        _parseHtmlFile: function(curNode, nextNode) {
            var self = this, images = {},
                $content = self._getContentNodes().clone(),
                getNodeIndex = function(nodeId){
                    var $node = $content.find("#" + nodeId),
                        $parent = $node.parent();
                    while (($parent.length > 0) && ($parent != $content)) {
                        $node = $node.parent();
                        $parent = $node.parent();
                    }
                    return $parent.length > 0 ? $parent.children().index($node) : -1;
                };

            if (curNode || nextNode) {
                var curIndex = curNode ? getNodeIndex(curNode) : -1,
                    nextIndex = nextNode ? getNodeIndex(nextNode) : -1;
                (curIndex == -1) ? (curIndex = 0) : (curIndex = curIndex + 1);
                (nextIndex == -1) ? (nextIndex = $content.children().length) : (nextIndex = nextIndex + 1);
                $content.each(function(i, item) {
                    if ((i < curIndex) || (i >= nextIndex)) {
                        $(item).remove();
                    }
                });
            }

            $content.find("img").each(function(i, item) {
                var src = $(item).attr("src");
                if (!(src in images)) {
                    images[src] = src;
                }
            });

            return {
                images : images,
                content: $content.html(),
                markdown: self._htmlToMarkdown($content.html())
            };
        },

        //Html页面转markdown
        convertHtml: function(html, callback) {
            var self = this, iCount = 0;
            self._loadHtmlContent(html);
            setTimeout(function() {
                iCount = iCount + 1;
                if (self._isLoadCompleted()) {
                    var section = self._parseHtmlFile();
                    if ($.isFunction(callback)) {
                        callback.call(self, section);
                    }
                } else if (iCount < self.timeCount) {
                    setTimeout(arguments.callee, 100);
                } else {
                    console.log("加载Html文件失败。。。。");
                }
            }, 10)
        },

        convertFile: function(url, callback) {
            var self = this, iCount = 0;
            self._loadHtmlFile(url);
            setTimeout(function() {
                iCount = iCount + 1;
                if (self._isLoadCompleted()) {
                    var section = self._parseHtmlFile();
                    if ($.isFunction(callback)) {
                        callback.call(self, section);
                    }
                } else if (iCount < self.timeCount) {
                    setTimeout(arguments.callee, 100);
                } else {
                    console.log("加载Html文件失败。。。。");
                }
            }, 10)
        },

        convertBook: function(index, callback) {
            var self = this, iCount = 0;

            self._loadHtmlFile(index);
            setTimeout(function() {
                iCount = iCount + 1;
                if (self._isLoadCompleted()) {
                    self._parseIndexFile();
                    self._parseSections();
                    if ($.isFunction(callback)) {
                        var iCount2 = 0;
                        setTimeout(function() {
                            iCount2 = iCount2 + 1;
                            if (self._isCompleted()) {
                                callback.call(self, self.sections);
                            } else if (iCount2 < self.timeCount) {
                                setTimeout(arguments.callee, self.timeout);
                            } else {
                                console.log("加载索引Html文件失败。。。。");
                            }
                        }, 10);
                    }
                } else if (iCount < self.timeCount) {
                    setTimeout(arguments.callee, 100);
                } else {
                    console.log("加载索引Html文件失败。。。。");
                }
            }, 10)
        },

        saveProject: function() {
            var self = this;
            $.ajax({
                url: "/project/edit",
                type: "POST",
                async : false,
                data: {
                    project_id: self.bookId,
                    name: self.bookName,
                    state: 0,
                    version: "",
                    description: self.description
                },
                //contentType: false,
                //processData: false,
                success: function (data) {
                    if (data.data) {
                        var bookData = data.data;
                        self.bookId = bookData.project_id;
                    }
                }
            });

        },

        saveSection: function(section) {
            var self = this, project_id = section.bookId,
                parentId = section.parent ? self.sections[section.parent].id : null;
            $.ajax({
                url: "/docs/save",
                type: "POST",
                async : false,
                data: {
                    project_id: self.bookId,
                    parentId: parentId,
                    documentName: section.title
                },
                //contentType: false,
                //processData: false,
                success: function (data) {
                    if (data.data) {
                        var secData = data.data;
                        section.id = secData.doc_id;
                    }
                }
            });
        },

        saveContent: function() {

        },

        uploadBook: function(index, callback) {

        }
    });

    $.extend({
        readLocalHtmlFile: function (filePath, callback) {
            var errorHandler = function(err) {
                console.info('文件读取失败！');
                console.info(err);
            }, readFileText = function (file) {
                var me = this, reader = new FileReader();
                reader.onloadend = function (e) {
                    if(this.readyState == FileReader.DONE) {
                        var htmlResult = this.result;
                        if ($.isFunction(callback)) {
                            callback.call(me, htmlResult)
                        }
                    }
                }
                reader.readAsText(file);
                //reader.readAsDataURL(file);
            }, initFileSystem = function(fs) {
                fs.root.getFile("test1234.txt", {create: true}, function (fileEntry) {
                    if (fileEntry.isFile) {
                        fileEntry.file(readFileText);
                    }
                }, errorHandler);
            }

            window.requestFileSystem(window.TEMPORARY, 5 * 1024, initFileSystem, errorHandler);
        },

        getHtmlConverter: function(options) {
            return new Converter(options);
        }
    });
})(jQuery);