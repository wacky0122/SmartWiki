<?php

namespace SmartWiki\Extentions\Calibre;

use Mockery\Exception;
use SmartWiki\Models\Calibre;
use SmartWiki\Models\CalibreDocument;
use SmartWiki\Models\Project;
use SmartWiki\Models\Document;
use HtmlParser\ParserDom;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Class CalibreConverter
 * @package SmartWiki\Extentions\Calibre
 */
class CalibreConverter {
    protected $calibre;
    protected $imgPath;

    protected $saveProjectFunc;
    protected $saveDocumentFunc;

    protected $project;
    protected $projectId;
    protected $documents = array();

    private $docHtmls = array();
    private $cssStyles = array();
    private $converter;

    private function toMarkdown($content) {
        if (empty($this->converter)) {
            $this->converter = new HtmlConverter(array("strip_tags"=>true));
        }
        $markdown = $this->converter->convert($content);
        //处理代码块显示错乱问题
        $markdown = preg_replace("/```(\r?\n)*/", "```\r\n", $markdown);
        $markdown = preg_replace("/(\r?\n)*```/", "\r\n```", $markdown);
        return $markdown;
    }

    /**
     * 解析Css样式内容，保存到cssStyles数组中
     * @param $content
     * @param $cssStyles
     */
    private function parseHtmlCssStyle($content, &$cssStyles) {
        $matches = array();
        //$pattern = "/([^\\.^\\n^{\\*/}]*[\\.][^\\{]*)\\{([^\\}]*)\\}/";
        $pattern = "/([\\.][^\\{]*)\\{([^\\}]*)\\}/";
        if (preg_match_all($pattern, $content, $matches)) {
            for ($i = 0, $len = count($matches[0]); $i < $len; $i++) {
                $cssName = trim($matches[1][$i]);
                $cssValue = trim($matches[2][$i]);
                if (!empty($cssName) && !empty($cssValue)) {
                    $cssNames = explode(",", $cssName);
                    foreach ($cssNames as $name) {
                        if (!empty(trim($name))) {
                            $tag = trim($name);
                            $cssStyles[$tag] = empty($cssStyles[$tag]) ? $cssValue :
                                $cssStyles[$tag].";".$cssValue;
                        }
                    }
                }
            }
        }
    }

    /**
     * 查找并解析css样式文件
     * @param $filePath
     * @param array $fileNames
     */
    private function parseHtmlCssFile($filePath, $fileNames = array()) {
        $handler = opendir($filePath);
        try {
            while(($file = readdir($handler)) !== false) {
                $fullPath = $filePath."/". $file;
                if($file == '.' || $file == '..') {
                    continue;
                } else if (is_dir($fullPath)) {
                    $this->parseHtmlCssFile($fullPath, $fileNames);
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $needParse = !empty($fileNames) ? in_array($file, $fileNames) :
                        (!empty($ext) && ($ext == "css"));
                    if ($needParse && file_exists($fullPath)) {
                        $content = trim(file_get_contents($fullPath));
                        $this->parseHtmlCssStyle($content, $this->cssStyles);
                    }
                }
            }
        } finally {
            closedir($handler);
        }
    }

    /**
     * 将html的style字符串解析成样式Map
     * @param $cssStyle
     */
    private function parseCssStyle($cssStyle, $classes, $classMap) {
        $parseFunc = function($cssStyle) {
            $cssStyles = array();
            if (!empty($cssStyle)) {
                $cssValues = explode(";", $cssStyle);
                foreach ($cssValues as $css) {
                    if (!empty($css) && (count(explode(":", $css)) > 1)) {
                        $values = explode(":", $css);
                        if (!empty($values[0]) && !empty($values[1])) {
                            $cssStyles[trim($values[0])] = trim($values[1]);
                        }
                    }
                }
            }
            return $cssStyles;
        };

        $newStyle = "";
        if (!empty($cssStyle)) {
            $newStyle = $newStyle.$cssStyle;
            if (substr($cssStyle, -1) != ";") {
                $newStyle = $newStyle.";";
            }
        }
        foreach ($classes as $class) {
            if (!empty($class) && isset($classMap[$class])) {
                $cssStyles = $parseFunc($classMap[$class]);
                if (!empty($cssStyles)) {
                    foreach ($cssStyles as $key=>$value) {
                        if (!empty($key) && !empty($value) && !stripos($newStyle, $key)) {
                            $newStyle = $newStyle.$key.":".$value.";";
                        }
                    }
                }
            }
        }
        return $newStyle;
    }

	/**
     * 解析Html标签中的classes属性
     * @param $nodeHtml
     * @return array
     */
    private function parseNodeClasses($nodeHtml) {
        $classes = array();
        if (!empty($nodeHtml)) {
            $tagName = substr(explode(" ", trim($nodeHtml))[0], 1);
            if (substr($tagName, -1) == ">") {
                $tagName = substr($tagName, 0, strlen($tagName) - 1);
            }
            $pattern = "/(<[^<]+class=\")([^\"]+)(\"[^>]*>)/";
            $oldClass = preg_replace($pattern, "$2", $nodeHtml);

            $oldClasses = ($oldClass == $nodeHtml) ? array() :
                explode(",", trim($oldClass));
            for ($i = 0, $len = count($oldClasses); $i < $len; $i++) {
                if (!empty($oldClasses[$i])) {
                    $classes[$i] = ($tagName . "." . trim($oldClasses[$i]));
                    $classes[$i + $len] = ".".trim($oldClasses[$i]);
                }
            }
            $classes[count($oldClasses) * 2] = $tagName;
        }
        return $classes;
    }

    /**
     * 解析Html各节点的class属性为内嵌style样式
     * @param $html
     */
    private function parseHtmlClasses($html) {
        $patterStyle = "/(<[^<]+style=\")([^\"]+)(\"[^>]*>)/"; // Style属性样式正则表达式
        //$patterNode = "/([^>]*)(<([a-z/][-a-z0-9_:.]*)[^>/]*(\\/*)>)([^<]*)/";
        $patterNode = "/([^>]*)(<([a-z\/][-a-z0-9_:.]*)[^>\/]*(\/*)>)([^<]*)/";
        $htmlResult = preg_replace_callback($patterNode, function($matches) use(&$patterStyle) {
            $tag = $matches[3];
            $tagHtml = $matches[2];
            if (!empty($tag) && (substr($tag, 0, 1) != "/")) {
                $classes = "body" == strtolower($tag) ? array() :
                    $this->parseNodeClasses($tagHtml);
                if (!empty($classes)) {
                    $nodeStyles = array();
                    if (preg_match($patterStyle, $tagHtml, $nodeStyles)) {
                        $newStyle = $this->parseCssStyle($nodeStyles[0][2], $classes, $this->cssStyles);
                        if (!empty($newStyle)) {
                            $tagHtml = preg_replace("/(style=\"[^\"]+\")/", "style=\"".$newStyle."\"", $tagHtml);
                        }
                    }  else {
                        $newStyle = $this->parseCssStyle(null, $classes, $this->cssStyles);
                        if (!empty($newStyle)) {
                            $tagHtml = preg_replace("/(class=\"[^\"]+\")/", "style=\"".$newStyle."\"", $tagHtml);
                        }
                    }

                }
            }
            return $matches[1].$tagHtml.$matches[5];
        }, $html);

        return empty($htmlResult) ? $html : $htmlResult;
    }

    private function parseHtmlContent($url) {
        if (empty($this->docHtmls[$url])) {
            $content = file_get_contents($url);
            //删除“pre”节点的属性
            $content = CalibreConverter::removeNodeAttribute($content, "pre");
            //解析Html各节点的class属性为内嵌style样式
            if (env('CALIBRE_CSS_PARSE', false)) {
                $content = $this->parseHtmlClasses(file_get_contents($url));
            }
            $html_dom = new ParserDom($content);
            $contentDiv = $html_dom->find("div.calibreEbookContent", 0);
            foreach ($contentDiv->find("div.calibreEbNavTop") as $div) {
                $div->node->parentNode->removeChild($div->node);
            }
            $this->docHtmls[$url] = $contentDiv->node;
        }
        $node = $this->docHtmls[$url];
        return empty($node) ? null : new ParserDom($node);
    }

    private function filterHtmlNodes($root, $begin, $end, &$isBegin, &$isEnd) {
        $remove = array();
        $target = $root->cloneNode();
        if ($root->hasChildNodes()) {
            $childNodes = $root->childNodes;
            for ($i = 0, $len = $childNodes->length; $i < $len; $i++) {
                $childNode = $childNodes->item($i);
                $isBegin = $isBegin || empty($begin) || $childNode === $begin;
                $isEnd = $isEnd || (!empty($end) && $childNode === $end);
                if (!$isEnd) {
                    $targetNode = $this->filterHtmlNodes($childNode, $begin, $end, $isBegin, $isEnd);
                    if ($isBegin) {
                        $target->appendChild($targetNode);
                        if (!$childNode->hasChildNodes()) {
                            $remove[count($remove)] = $i;
                        }
                    } else {
                        $remove[count($remove)] = $i;
                    }
                } else {
                    break;
                }
            }

            for ($i = count($remove) - 1; $i >= 0; $i--) {
                $root->removeChild($childNodes->item($remove[$i]));
            }
        }
        return $target;
    }

    private function filterHtmlContent($url, $begin, $end) {
        $root = $this->parseHtmlContent($url);
        $rootNode = $root->node;
        $findEndNode = function($nodes) use (&$root) {
            $endNode = null;
            if (!empty($nodes)) {
                for ($i = 0, $len = count($nodes); $i < $len; $i++) {
                    $endNode = $root->find("#".$nodes[$i], 0);
                    if ($endNode && !empty($endNode)) {
                        $endNode = $endNode->node;
                        break;
                    }
                }
            }
            return $endNode;
        };
        $beginNode = empty($begin) ? null : $root->find("#".$begin, 0);
        $beginNode = !$beginNode || empty($beginNode) ? null : $beginNode->node;
        $endNode = empty($end) ? null : $findEndNode($end);

        if (empty($beginNode) && empty($endNode)) {
            return $root;
        } else {
            $isEnd = false;
            $isBegin = empty($beginNode);
            $htmlNode = $this->filterHtmlNodes($rootNode, $beginNode, $endNode, $isBegin, $isEnd);
            return new ParserDom($htmlNode);
        }
    }

    private function replaceImagePath(&$content, $path) {
        $imageFiles = array();
        foreach ($content->find("img") as $img) {
            if (!empty($img) && !empty($img->getAttr("src"))) {
                $file = substr(strrchr($img->getAttr("src"), "/"), 1);
                $img->node->setAttribute("src", "/".$path."/".$file);
                $imageFiles[count($imageFiles)] = $file;
            }
        }
        return empty($imageFiles) ? null : $imageFiles;
    }

    private function parseIndexHtml($node, $parent, &$documents) {
        if (!empty($node) && !empty($node->node)
            && (XML_ELEMENT_NODE === $node->node->nodeType)) {
            $parentIndex = $parent;
            if ($node->node->tagName == "li") {
                $a = $node->find("a", 0);
                $aNode = $a->node;
                if (!empty($a) && !empty($aNode) &&
                    ($aNode->parentNode === $node->node)) {
                    $document = array();
                    $document["doc_name"] = $a->getPlainText();
                    $document["parent_id"] = $parentIndex;

                    $href = $a->getAttr("href");
                    $nodeId = strrchr($href, "#");
                    if (!empty($nodeId)) {
                        $document["doc_node"] = substr($nodeId, 1);
                        $document["doc_url"] = substr($href, 0, strlen($href) - strlen($nodeId));
                    } else {
                        $document["doc_node"] = null;
                        $document["doc_url"] = $href;
                    }
                    $document["calibre_url"] = substr(strrchr($href, "/"), 1);
                    if(!empty($document["doc_name"])) {
                        $documents[count($documents)] = $document;
                        $parentIndex = count($documents) - 1;
                    }
                }
            }
            foreach($node->node->childNodes as $element) {
                $this ->parseIndexHtml(new ParserDom($element), $parentIndex, $documents);
            }
        }
    }

    private function parseProject() {
        $coverFile = null;
        $project = new Project();
        $this->calibre->convertToProject($project);
        if (!empty($project->project_cover)) {
            $coverFile = substr(strrchr($project->project_cover, "/"), 1);
            $project->project_cover = "/".$this->imgPath."/".$coverFile;
        }

        //保存成功后回调复制项目的封面图片
        $thisConverter = $this;
        $callBackFunc = function() use (&$coverFile, &$thisConverter) {
            if (!empty($coverFile)) {
                $thisConverter->copyImageFiles(array($coverFile));
            }
        };

        $this->project = $project;
        //回调保存项目函数，并返回保存项目结果
        if (!empty($this->saveProjectFunc)) {
            $project = call_user_func($this->saveProjectFunc,
                $project, $this->calibre, $callBackFunc);
            if (!empty($project)) {
                $this->project = $project;
                $this->projectId = $project->project_id;
                $this->calibre->project_id = $project->project_id;
            }
        }
    }

    private function parseDocument() {
        $getUrlNode = function($doc, &$url, &$node) {
            $url = $doc["doc_url"];
            $node = $doc["doc_node"];
        };
        $getEndNodeIds = function($documents, $url, $index) use (&$getUrlNode) {
            $endNodes = array();
            for ($i = $index + 1, $len = count($documents); $i < $len; $i++) {
                $curUrl = ""; $curNode = "";
                $getUrlNode($documents[$i], $curUrl, $curNode);
                if ($curUrl != $url) {
                    break;
                } else if (!empty($curNode)) {
                    $endNodes[count($endNodes)] = $curNode;
                }
            }
            return $endNodes;
        };

        //解析stylesheet.css样式文件
        if (env('CALIBRE_CSS_PARSE', false)) {
            $rootPath = public_path($this->calibre->file_path);
            $this->parseHtmlCssFile($rootPath, array("stylesheet.css"));
        }

        $documents = array();
        $indexFile = $this->calibre->file_path."/".substr(strrchr($this->calibre->url, "/"), 1);
        $html = $this->filterHtmlContent(public_path($indexFile), null, null);
        $this->parseIndexHtml($html, null, $documents);
        for ($i = 0, $len = count($documents); $i < $len; $i++) {
            $document = $documents[$i];

            $url = ""; $beginNode = "";
            $getUrlNode($document, $url, $beginNode);
            $endNodes = $getEndNodeIds($documents, $document["doc_url"], $i);
            $url = public_path($this->calibre->file_path. "/". $url);

            $content = $this->filterHtmlContent($url, $beginNode, $endNodes);
            $docImages = $this->replaceImagePath($content, $this->imgPath);
            $document["doc_content"] = $this->toMarkdown($content->innerHtml());

            $newDocument = new Document();
            $newDocument->doc_name = $document["doc_name"];
            $newDocument->doc_sort = $i + 1;
            $newDocument->project_id = $this->project->project_id;
            $newDocument->doc_content = $document["doc_content"];
            $newDocument->calibre_url = $document["calibre_url"];

            if (!empty($document["parent_id"])) {
                $parent = $this->documents[$document["parent_id"]];
                if (!empty($parent->doc_id)) {
                    $newDocument->parent_id = $parent->doc_id;
                }
            }
            $this->documents[$i] = $newDocument;

            //保存成功后回调复制文档中关联的图片
            $thisConverter = $this;
            $callBackFunc = function() use (&$docImages, &$thisConverter) {
                if(!empty($docImages)) {
                    $thisConverter->copyImageFiles($docImages);
                }
            };

            //回调保存文档函数，并返回保存文档结果
            if (!empty($this->saveDocumentFunc)) {
                $newDocument = call_user_func($this->saveDocumentFunc,
                    $newDocument, $this->calibre, $callBackFunc);
                if (!empty($newDocument)) {
                    $this->documents[$i] = $newDocument;

                }
            }

            //备份Calibre文档解析结果的Html和markdown到Calibre_Doc表中
            $calibreDoc = CalibreDocument::whereCalibreId($this->calibre->calibre_id)
                ->whereDocName($newDocument->doc_name)->first();
            if (empty($calibreDoc)) {
                $calibreDoc = new CalibreDocument();
            }
            $calibreDoc->calibre_id = $this->calibre->calibre_id;
            $calibreDoc->calibre_url = $newDocument->calibre_url;
            $calibreDoc->calibre_html = $content->innerHtml();
            $calibreDoc->doc_name = $newDocument->doc_name;
            $calibreDoc->doc_content = $document["doc_content"];
            $calibreDoc->doc_id = $newDocument->doc_id;
            $calibreDoc->parent_id = $newDocument->parent_id;

            $calibreDoc->addOrUpdate();
        }
        $this->project->doc_count = count($this->documents);
    }

    public function copyImageFiles($imgFiles = null) {
        $imgPath = public_path($this->imgPath);
        $allowExt = explode('|', 'jpg|jpeg|gif|png');
        $copyFunc = function($filePath) use (&$imgPath, &$imgFiles, &$allowExt, &$copyFunc) {
            $handler = opendir($filePath);
            try {
                while(($file = readdir($handler)) !== false) {
                    $fullPath = $filePath."/". $file;
                    if($file == '.' || $file == '..') {
                        continue;
                    } else if (is_dir($fullPath)) {
                        $copyFunc($fullPath);
                    } else {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $allowCopy = !empty($imgFiles) ? in_array($file, $imgFiles) :
                            (!empty($ext) && in_array($ext, $allowExt));
                        if ($allowCopy && !file_exists($imgPath . "/" . $file)) {
                            @copy($fullPath, $imgPath . "/" . $file);
                        }
                    }
                }
            } finally {
                closedir($handler);
            }
        };

        $file_path = $this->calibre->file_path;
        if (!empty($file_path)) {
            @mkdir($imgPath, 0777, true);
            $copyFunc(public_path($file_path));
        }
    }

    public function __construct(callable $saveProjectFunc, callable $saveDocumentFunc) {
        $this->saveProjectFunc = $saveProjectFunc;
        $this->saveDocumentFunc = $saveDocumentFunc;
    }

    public function convertCalibre(&$calibre, $imgPath) {
        $this->calibre = $calibre;
        $this->imgPath = $imgPath;

        $this->project = null;
        $this->projectId = null;
        $this->documents = array();
        $this->docHtmls = array();
        $this->cssStyles = array();

        $this->parseProject();
        $this->parseDocument();
    }

    public function getProject() {
        return $this->project;
    }

    public function getDocuments($index = null) {
        $documents = $this->documents;
        return empty($index) ? $documents : $documents[$index];
    }

    /**
     * 删除HTML标签对应节点的所有属性
     * @param $content
     * @param $tag
     * @return mixed
     */
    public static function removeNodeAttribute($content, $tag) {
        $patterNode = "/([^>]*)(<([a-z\/][-a-z0-9_:.]*)[^>\/]*(\/*)>)([^<]*)/";
        $htmlResult = preg_replace_callback($patterNode, function($matches) use(&$tag) {
            $tagName = $matches[3];
            if (!empty($tagName) && (substr($tagName, 0, 1) != "/")
                && (strtolower($tagName) == $tag)) {
                return $matches[1]."<".$tag.">".$matches[5];
            }
            return $matches[1].$matches[2].$matches[5];
        }, $content);
        return $htmlResult;
    }

    /**
     * 处理代码块因pre节点导致的错乱
     * @param $content
     * @return mixed
     */
    public static function dealCodePartContent($content) {
        $content = CalibreConverter::removeNodeAttribute($content, "pre");
        $content = preg_replace("/```(\r?\n)*<pre>(\r?\n)*```/", "```\r\n", $content);
        $content = preg_replace("/(\r?\n)*```(\r?\n)*```/", "\r\n```", $content);

        $content = preg_replace("/```(\r?\n)*/", "```\r\n", $content);
        $content = preg_replace("/(\r?\n)*```/", "\r\n```", $content);

        return $content;
    }
}
