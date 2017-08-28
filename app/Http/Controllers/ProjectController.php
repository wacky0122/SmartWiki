<?php
/**
 * Created by PhpStorm.
 * User: lifeilin
 * Date: 2016/10/25
 * Time: 11:32
 */

namespace SmartWiki\Http\Controllers;

use DB;
use Log;
use Cache;
use SmartWiki\Models\CalibreDocument;
use ZipArchive;
use XMLReader;
use SmartWiki\Models\Member;
use SmartWiki\Models\Project;
use SmartWiki\Models\Calibre;
use SmartWiki\Models\Document;
use SmartWiki\Models\Relationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use SmartWiki\Extentions\Calibre\CalibreConverter;

class ProjectController extends Controller
{
    /**
     * 创建项目
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        //如果非管理员用户并且非普通用户则禁止创建项目
        if($this->member->group_level != 0 && $this->member->group_level != 1){
            abort(403);
        }

        $projectName = trim($this->request->input('projectName'));
        $description = trim($this->request->input('description',null));
        $isPasswd = $this->request->input('projectPasswd','1');
        $passwd = trim($this->request->input('projectPasswdInput',null));

        $project = new Project();
        $project->project_name = $projectName;
        $project->description = $description;
        $project->project_open_state = $isPasswd;
        $project->project_password = $passwd;
        $project->create_at = $this->member_id;

        try{
            $project->addOrUpdate();

        }catch (\Exception $ex){
            if($ex->getCode() == 500){
                return $this->jsonResult(40205,null,$ex->getMessage());
            }else{
                return $this->jsonResult($ex->getCode());
            }
        }
        $this->data = $project->toArray();

        $this->data['doc_count'] = 0;

        $view = view('widget.project',$this->data);
        $this->data = array();

        $this->data['body'] = $view->render();

        return $this->jsonResult(20002,$this->data);
    }

    /**
     * 删除项目
     * @return \Illuminate\Contracts\View\Factory|JsonResponse|\Illuminate\View\View
     */
    public function delete($id)
    {
        $this->data['member_projects'] = true;
        $project_id = intval($id);
        if ($project_id <= 0) {
            if($this->request->ajax()){
                return $this->jsonResult(50502);
            }
            abort(404);
        }
        $project = Project::find($project_id);
        if (empty($project)) {
            if($this->request->ajax()) {
                return $this->jsonResult(40206);
            }
            abort(404);
        }
        //如果不是项目的拥有者并且不是超级管理员
        if (!Project::isOwner($project_id,$this->member->member_id) && $this->member->group_level != 0) {
            if($this->request->ajax()) {
                return $this->jsonResult(40305);
            }
            abort(403);
        }

        if($this->isPost()) {
            $password = $this->request->get('password');
            $member = Member::find($this->member_id);
            //如果密码错误
            if(password_verify($password,$member->member_passwd) === false){
                return $this->jsonResult(40606);
            }
            try{
                Project::deleteProjectByProjectId($project_id);
                return $this->jsonResult(0);
            }catch (\Exception $ex){
                if($ex->getCode() == 500){
                    Log::error($ex->getMessage(),['trace'=>$ex->getTrace(),'file'=>$ex->getFile(),'line'=>$ex->getLine()]);
                    return $this->jsonResult(500,null,'删除失败');
                }else{
                    return $this->jsonResult($ex->getCode());
                }
            }
        }
        $this->data['project'] = $project;

        return view('project.delete',$this->data);
    }
    /**
     * 编辑项目或创建
     * @param int $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|JsonResponse
     * @throws AuthorizationException
     */
    public function edit($id = null)
    {
        $project_id = intval($id);
        //如果是访客则不能创建项目
        if($project_id <=0 && is_can_create_project($this->member_id) === false){
            abort(403);
        }
        $project = null;
        //如果项目不存在
        if($project_id > 0 && empty($project = Project::find($id)) ){
            if($this->isPost()){
                return $this->jsonResult(40206);
            }else{
                abort(404);
            }
        }

        //如果没有编辑权限
        if($project_id > 0 &&  $this->member->group_level != 0 && Project::hasProjectEdit($project_id,$this->member_id) === false){

            if($this->isPost()){
                return $this->jsonResult(40305);
            }else{
                abort(403);
            }
        }
        //如果不是项目的拥有者并且不是超级管理员
        if ($project_id > 0 && !Project::isOwner($project_id,$this->member->member_id) && $this->member->group_level != 0) {

            abort(403);
        }

        //如果是修改项目
        if($this->isPost()){
            $name = trim($this->request->input('name'));
            $description = trim($this->request->input('description'));
            $open_state = $this->request->input('state');
            $password = $this->request->input('password');
            $version = $this->request->input('version');
            if(empty($project)) {
                $project = new Project();
            }
            $project->project_name = $name;
            $project->description = $description;
            $project->project_open_state = $open_state;
            $project->project_password = $password;
            $project->version = $version;
            $project->create_at = $this->member_id;

            try{
                if($project->addOrUpdate()) {
                    $data['project_id'] = $project->project_id;
                    $data['url'] = route('project.edit',['id'=>$project->project_id]);

                    return $this->jsonResult(0,$data);
                }else{
                    return $this->jsonResult(500);
                }
            }catch (\Exception $ex){
                if($ex->getCode() == 500){
                    return $this->jsonResult(40205,null,$ex->getMessage());
                }else{
                    return $this->jsonResult((int)$ex->getCode());
                }
            }
        }
        $this->data['title'] = '编辑项目';

        if(empty($project)){
            $project = new Project();
            $project->project_open_state = 0;
            $this->data['title'] = '添加项目';
            $this->data['is_owner'] = false;
        }else{
            $this->data['is_owner'] = Project::isOwner($project_id,$this->member->member_id) ;
        }

        $this->data['project'] = $project;
        $this->data['member_projects'] = true;

        return view('project.edit',$this->data);
    }

    /**
     * 项目参与成员列表
     * @param int $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function members($id)
    {
        $project_id = intval($id);

        if(empty($project_id)){
            abort(404);
        }

        $project = Project::find($project_id);
        if(empty($project)){
            abort(404);
        }

        //如果不是项目的拥有者并且不是超级管理员
        if (!Project::isOwner($project_id,$this->member->member_id) && $this->member->group_level != 0) {
            return $this->jsonResult(40305);
        }

        $this->data = $project;
        $this->data['member'] = $this->member;
        $this->data['member_projects'] = true;
        $this->data['users'] = Project::getProjectMemberByProjectId($project_id);
        return view('project.members',$this->data);
    }

    /**
     * 添加或删除项目组用户
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember($id)
    {
        $project_id = intval($id);
        $type = trim($this->request->input('type'));
        $account = trim($this->request->input('account'));

        if (empty($project_id)) {
            return $this->jsonResult(50502);
        }
        $project = Project::find($project_id);
        if (empty($project)) {
            return $this->jsonResult(40206);
        }
        //如果不是项目的拥有者并且不是超级管理员
        if (!Project::isOwner($project_id,$this->member->member_id) && $this->member->group_level != 0) {
            return $this->jsonResult(40305);
        }
        $member = Member::findNormalMemberOfFirst([['account', '=', $account]]);
        if (empty($member)) {
            return $this->jsonResult(40506);
        }

        if($member->state == 1){
            return $this->jsonResult(40511);
        }
        $data = null;
        $rel = Relationship::where('project_id', '=', $project_id)->where('member_id', '=', $member->member_id)->first();
        //如果是添加成员
        if (strcasecmp($type, 'add') === 0) {
            if (empty($rel) === false) {
                return $this->jsonResult(40801);
            }
            $rel = new Relationship();
            $rel->project_id = $project_id;
            $rel->member_id = $member->member_id;
            $rel->role_type = 0;
            $result = $rel->save();

            if($result) {
                $item = new \stdClass();

                $item->role_type  = $rel->role_type;
                $item->account    = $member->account;
                $item->member_id  = $member->member_id;
                $item->email      = $member->email;
                $item->headimgurl = $member->headimgurl;
                $this->data['item'] = $item;

                $data = view('widget.project_member',$this->data)->render();
            }
        } else {
            $result = empty($rel) === false ? $rel->delete() : false;
        }

        return $result ? $this->jsonResult(0,$data) : $this->jsonResult(500);
    }

    /**
     * 退出项目
     * @param int $id
     * @return JsonResponse
     */
    public function quit($id)
    {
        $project_id = intval($id);
        if (empty($project_id)) {
            return $this->jsonResult(50502);
        }
        if($this->member->group_level === 2){
            return $this->jsonResult(403);
        }

        $project = Project::find($project_id);
        if (empty($project)) {
            return $this->jsonResult(40206);
        }

        $relationship = Relationship::where('project_id','=',$project_id)->where('member_id','=',$this->member_id)->first();


        //如果是项目参与者，则退出
        if(empty($relationship) === false && $relationship->role_type === 0){

            $result = $relationship->delete();
            return $result ? $this->jsonResult(0) : $this->jsonResult(500);
        }
        return $this->jsonResult(500,null,'非参与者无法退出');
    }
    /**
     * 项目转让
     * @param int $id
     * @return JsonResponse
     */
    public function transfer($id)
    {
        $project_id = intval($id);
        $account = trim($this->request->input('account'));

        if (empty($project_id)) {
            return $this->jsonResult(50502);
        }
        $project = Project::find($project_id);
        if (empty($project)) {
            return $this->jsonResult(40206);
        }

        //如果不是项目的拥有者并且不是超级管理员
        if (!Project::isOwner($project_id,$this->member->member_id) && $this->member->group_level != 0) {
            return $this->jsonResult(40305);
        }

        $member = Member::findNormalMemberOfFirst([['account', '=', $account]]);

        if (empty($member)) {
            return $this->jsonResult(40506);
        }

        //将拥有用户降级为参与者
        $rel = Relationship::where('project_id', '=', $project_id)->where('role_type', '=', 1)->first();

        $rel->role_type = 0;

        if(!$rel->save()){
            return $this->jsonResult(40802);
        }
        //如果目标用户存在则升级为拥有者
        $newRel = Relationship::where('project_id', '=', $project_id)->where('member_id', '=', $member->member_id)->first();
        if(empty($newRel)){
            $newRel = new Relationship();
        }

        $newRel->project_id = $project_id;
        $newRel->member_id = $member->member_id;
        $newRel->role_type = 1;
        if(!$newRel->save()){
            return $this->jsonResult(40802);
        }
        return $this->jsonResult(0);

    }

    private function getCalibreProjectUrl($webPath) {
        $url = null;
        $path = public_path($webPath);
        $handler = opendir($path);
        try {
            while(($file = readdir($handler)) !== false) {
                $sub_dir = $path . DIRECTORY_SEPARATOR . $file;
                if($file == '.' || $file == '..' || is_dir($sub_dir)) {
                    continue;
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if(!empty($ext) && ($ext == "html")) {
                        $url = url($webPath."/".$file);
                        break;
                    }
                }
            }
        } finally {
            closedir($handler);
        }
        return $url;
    }

    private function readCalibreMetadata($metaFile) {
        $calibre = new Calibre();
        if (file_exists($metaFile)) {
            $reader = new XMLReader();
            $reader->open($metaFile);
            while($reader->read()) {
                if($reader->nodeType == XMLReader::ELEMENT){
                    $nodeName = $reader->name;
                }
                if($reader->nodeType == XMLReader::TEXT && !empty($nodeName)){
                    switch($nodeName){
                        case 'dc:title':
                            $calibre->title = $reader->value;
                            break;
                        case 'dc:creator':
                            $calibre->author = $reader->value;
                            break;
                        case 'dc:date':
                            $calibre->date = $reader->value;
                            break;
                        case 'dc:publisher':
                            $calibre->publisher = $reader->value;
                            break;
                        case 'dc:description':
                            $calibre->description = $reader->value;
                            break;
                    }
                }
            }
        }
        return empty($calibre->title) ? null : $calibre;
    }


    private function generateCalibres(&$calibres, $SourceDir, $targetDir = "uploads/calibre") {
        $proFiles = array();
        $handler = opendir($SourceDir);
        try {
            $allowExt = explode('|', 'jpg|jpeg|gif|png|opf|zip');
            while(($file = readdir($handler)) !== false) {
                $sub_dir = $SourceDir . DIRECTORY_SEPARATOR . $file;
                if($file == '.' || $file == '..') {
                    continue;
                } else if(is_dir($sub_dir)) {
                    $this->generateCalibres($calibres, $sub_dir, $targetDir);
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if(!empty($ext) && in_array($ext, $allowExt)) {
                        switch ($ext) {
                            case 'zip':
                                $proFiles["zip"] = $file;
                                break;
                            case 'opf':
                                $proFiles["metadata"] = $file;
                                break;
                            default:
                                $proFiles["cover"] = $file;
                                break;
                        }
                    }
                }
            }
        } finally {
            closedir($handler);
        }

        if (!(empty($proFiles["zip"]) || empty($proFiles["metadata"]))) {
            $metaFile = $SourceDir.DIRECTORY_SEPARATOR.$proFiles["metadata"];
            $calibre = $this->readCalibreMetadata($metaFile);
            if (!empty($calibre) && (Calibre::getCalibreCountByTitle($calibre->title) == 0)) {
                $webPath = $targetDir."/".date('Ym').uniqid();
                $tempPath = public_path($webPath);
                @mkdir($tempPath, 0777, true);
                foreach ($proFiles as $file) {
                    $fullPath = $tempPath.DIRECTORY_SEPARATOR.$file;
                    @copy($SourceDir.DIRECTORY_SEPARATOR.$file, $fullPath);
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    if ("zip" == $ext) {
                        $zip = new ZipArchive();
                        if ($zip->open($fullPath)) {
                            $zip->extractTo($tempPath);
                            $zip->close();
                        }
                    }
                }
                //$metaFile = $tempPath.DIRECTORY_SEPARATOR.$proFiles["metadata"];
                //$calibre = $this->readCalibreMetadata($metaFile, $webPath);
                $calibre->file_path = $webPath;
                $calibre->url = $this->getCalibreProjectUrl($webPath);
                if (!is_null($calibre) && !empty($calibre->url)) {
                    if (!empty($proFiles["cover"])) {
                        $calibre->cover = url($webPath."/".$proFiles["cover"]);
                    }
                    $calibre->addOrUpdate();
                    $calibres[count($calibres)] = $calibre;
                }
            }
        }
        return null;
    }

    private function deleteCalibreFile($path) {
        $op = dir($path);
        try {
            while (false != ($item = $op->read())) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                if (is_dir($op->path . '/' . $item)) {
                    $this->deleteCalibreFile($op->path . '/' . $item);
                } else {
                    unlink($op->path . '/' . $item);
                }

            }
        } finally {
            $op->close();
        }
        rmdir($path);
    }

    /**
     * 读取Calibre书库列表并复制解压项目到uploads/calibre下
     */
    public function reloadCalibres() {
        $calibres = array();
        $calibrePath = env('CALIBRE_BOOK_PATH',"F:/EBLibrary");
        if (is_dir($calibrePath)) {
            $this->generateCalibres($calibres, iconv('UTF-8','gbk', $calibrePath));
            $data['success'] = true;
            $data['message'] = '导入Calibre库成功!';
        } else {
            $data['success'] = false;
            $data['message'] = $calibrePath.'书库不存在!';
        }
        return $this->response->json($data);
    }

    /**
     * 删除Calibre项目
     * @param $id
     */
    public function deleteCalibre($id) {
        $calibre = Calibre::whereCalibreId($id)->first();
        if (!empty($calibre)) {
            $webPath = $calibre->file_path;
            if (Calibre::deleteProjectByCalibreId($id)) {
                //删除上传的文件
                try {
                    CalibreDocument::deleteDocByCalibreId($id);
                    $this->deleteCalibreFile(public_path($webPath));
                }catch (\Exception $ex){
                    if($ex->getCode() == 500){
                        Log::error($ex->getMessage(),['trace'=>$ex->getTrace(),'file'=>$ex->getFile(),'line'=>$ex->getLine()]);
                        return $this->jsonResult(500,null,'删除失败');
                    }else{
                        return $this->jsonResult($ex->getCode());
                    }
                }
            }
        }
        $data['success'] = true;
        $data['message'] = '删除Calibre图书成功!';

        return $this->response->json($data);
    }

    public function queryCalibre($id) {
        $calibre = Calibre::whereCalibreId($id)->first();
        return $this->response->json($calibre);
    }

    /*
    private function copyProject($source, &$target) {
        $target->project_name = $source->project_name;
        $target->description = $source->description;
        $target->project_open_state = $source->project_open_state;
        $target->project_cover = $source->project_cover;
        $target->project_author = $source->project_author;
        $target->project_publisher = $source->project_publisher;
        $target->project_date = $source->project_date;
    }
    */

    private function getCalibreConverter() {
        $self = $this;
        $saveProjectFunc = function($project, $calibre, callable $callback) use (&$self) {
            if (!empty($project)) {
                $DbProject = empty($project->project_id) ? null :
                    Project::whereProjectId($project->project_id) ->first();
                if (empty($DbProject)) {
                    //$saveProject = new Project();
                    //$self->copyProject($project, $saveProject);
                    $project->create_at = $self->member_id;
                    $project->addOrUpdate();
                    $calibre->project_id = $project->project_id;
                    $calibre->save();
                    $DbProject = $project;

                    if (!empty($callback)) {
                        call_user_func($callback);
                    }
                }
                return $DbProject;
            }
        };
        $saveDocumentFunc = function($document, $calibre, callable $callback) use (&$self) {
            $projectId = empty($document->project_id) ?
                $calibre->project_id : $document->project_id;
            if (!empty($document) && !empty($projectId)) {
                $query = Document::whereProjectId($projectId)->where(
                    "doc_name", "=", "$document->doc_name");
                if (!empty($document->parent_id)) {
                    $query = $query->where("parent_id", "=", $document->parent_id);
                }
                $DbDocument = !empty($query) ? $query->first() : null;
                if (empty($DbDocument)) {
                    $document->project_id = $projectId;
                    $document->create_at = $self->member_id;
                    $document->save();

                    if (!empty($callback)) {
                        call_user_func($callback);
                    }

                    return $document;
                } else {
                    return $DbDocument;
                }
            }
        };
        return new CalibreConverter($saveProjectFunc, $saveDocumentFunc);
    }

    public function convertCalibre($calibre, $converter = null) {
        $success = false;
        if (!empty($calibre) && !empty($calibre->title)) {
            try {
                $calibre->state = 2;
                $calibre->save();

                $imgPath = "uploads/".date('Ym')."/".uniqid();
                $converter = !empty($converter) ? $converter :
                    $this->getCalibreConverter();
                $converter->convertCalibre($calibre, $imgPath);

                $success = true;
                //$project = $converter->getProject();
                //$documents = $converter->getDocuments();
                //$project->doc_count = count($documents);

                //删除空白文档
                $project_id = $calibre->project_id;
                $document = Document::whereProjectId($project_id)
                    ->where("doc_sort", "=", 0)->first();
                if (!empty($document)) {
                    Document::deleteDocument($document->doc_id);
                }
            } finally {
                $calibre->state = ($success ? 1 : 0);
                $calibre->save();
            }
        }
        return $success;
    }

    public function importCalibre($id) {
        $calibre = Calibre::whereCalibreId($id)->first();
        if (empty($calibre)) {
            $data['message'] = '未找到相关Calibre记录!';
        } else if ($calibre->state != 0) {
            $data['message'] = '该记录已导入或其他用户正在导入中。。。!';
        } else if ($this->convertCalibre($calibre)) {
            $data['success'] = true;
            $data['message'] = '导入《'.$calibre->title.'》成功!';
        }
        return $this->response->json($data);
    }

    public function importAllCalibre() {
        $success = 0; $failed = 0;
        $converter = $this->getCalibreConverter();
        $calibres = Calibre::whereState(0)->get(array("calibre_id"));
        foreach ($calibres as $item) {
            $calibre = Calibre::whereCalibreId($item->calibre_id)->first();
            if (!empty($calibre) && ($calibre->state == 0)) {
                $boolean = false;
                try {
                    $boolean = $this->convertCalibre($calibre, $converter);
                } catch(\Exception $ex) {
                    Log::error($ex->getMessage(),['trace'=>$ex->getTrace(),'file'=>$ex->getFile(),'line'=>$ex->getLine()]);
                }
                if ($boolean) {
                    $success++;
                } else {
                    $failed++;
                }
            }
        }
        $data['success'] = true;
        $data['message'] = '导入完成，导入成功'.$success.'，失败'.$failed.'！';
        return $this->response->json($data);
    }

    /**
     * 替换calibre书库中的part0001.html格式的链接地址
     * @param $id
     * @return mixed
     */
    public function replaceCalibreUrl($id) {
        $documents = Document::whereProjectId($id)->get();
        $calibreUrl = Document::getCalibreUrlFromCache($id);
        if (!empty($documents) && !empty($calibreUrl)) {
            foreach ($documents as $document) {
                $matches = array();
                $content = $document->doc_content;
                if (!empty($content) &&
                    preg_match_all("/\\]\\(part(\d+)\\.html(#.*)?\\)/", $content, $matches)) {
                    foreach($matches[0] as $match) {
                        $url = substr($match, 2, strlen($match) - 3);
                        if (!empty($calibreUrl[$url])) {
                            $content = str_replace($match, "](".$calibreUrl[$url].")", $content);
                        } else {
                            $content = str_replace($match, "](#)", $content);
                        }
                    }
                    $document->doc_content = $content;
                    $document->save();
                }
            }
        }
        $data['success'] = true;
        $data['message'] = '文档处理完成，已将calibre链接替换完毕！';
        return $this->response->json($data);
    }

    /**
     * 处理代码块的pre标签问题
     * @param $id
     * @return mixed
     */
    public function dealCalibreCode($id) {
        $success = 0;
        $documents = Document::whereProjectId($id)->get();
        foreach ($documents as $document) {
            $content = $document->doc_content;
            $content = CalibreConverter::dealCodePartContent($content);
            if ($content != $document->doc_content) {
                $document->doc_content = $content;
                $document->save();
                $success++;
            }
        }
        $data['success'] = true;
        $data['message'] = "文档处理完成，已将代码块pre标签处理完毕，成功处理".$success."！";
        return $this->response->json($data);
    }
}