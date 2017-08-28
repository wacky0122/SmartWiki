<?php

namespace SmartWiki\Models;

use DB;
use Cache;
use Carbon\Carbon;
use SmartWiki\Exceptions\DataNullException;
use SmartWiki\Exceptions\FormatException;
use SmartWiki\Exceptions\ResultException;

/**
 * SmartWiki\Models\Calibre
 * @property integer $calibre_id
 * @property integer $project_id
 * @property string $title 项目名称
 * @property string $author 项目作者
 * @property string $date 出版时间
 * @property string $cover 项目封面
 * @property string $publisher 出版社
 * @property string $description 项目描述
 * @property string $url
 * @property string $create_time
 * @property string $file_path
 * @property integer $state 项目状态0没有导入，1已导入
 * @method static \Illuminate\Database\Query\Builder|Calibre whereTitle($value)
 * @method static \Illuminate\Database\Query\Builder|Calibre whereState($value)
 * @method static \Illuminate\Database\Query\Builder|Calibre whereCalibreId($value)
 * @mixin \Eloquent
 */
class Calibre extends ModelBase
{
    protected $table = 'calibre';
    protected $primaryKey = 'calibre_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['calibre_id'];

    public $timestamps = false;

    /**
     * 删除项目以及项目相关的文档
     * @param $calibre_id
     * @return bool
     * @throws DataNullException|ResultException
     */
    public static function deleteProjectByCalibreId($calibre_id)
    {
        $project = Calibre::find($calibre_id);
        if(empty($project)){
            throw new DataNullException('项目不存在',40206);
        }
        DB::beginTransaction();
        try {
            $project->delete();
            DB::commit();
            return true;
        }catch (\Exception $ex){
            DB::rollBack();
            throw new ResultException($ex->getMessage(),500);
        }

    }

    /**
     * 查询相同标题记录数
     * @param $title
     * @return mixed
     */
    public static function getCalibreCountByTitle($title) {
        return DB::table('calibre')->whereTitle($title)->count();
    }

    /**
     * 根据状态查询记录数
     * @param $state
     */
    public static function getCalibreCountByState($state, $title) {
        if (empty($title)) {
            return DB::table('calibre')->whereState($state)->where("title", "like", "%".$title."%")->count();
        } else {
            return DB::table('calibre')->whereState($state)->where("title", "like", "%".$title."%")->count();
        }
    }

    /**
     * 删除同名称的项目
     * @param $title
     * @return bool
     * @throws ResultException
     */
    public static function deleteProjectByTitle($title)
    {
        DB::beginTransaction();
        try {
            Calibre::whereTitle($title)->delete();
            DB::commit();
            return true;
        }catch (\Exception $ex){
            DB::rollBack();
            throw new ResultException($ex->getMessage(),500);
        }
    }

    /**
     * 添加或更新项目
     * @return bool
     * @throws \Exception
     */
    public function addOrUpdate()
    {
        if(empty($this->title) || mb_strlen($this->title) < 2 || mb_strlen($this->title) > 100){
            throw new FormatException('项目名称必须在2-100字之间',40201);
        }
        if(empty($this->url)) {
            throw new FormatException('项目地址不能为空',40201);
        }
        if(mb_strlen($this->description) > 2500){
            throw new FormatException('项目描述不能超过2500字',40202);
        }

        if (empty($this->state)) {
            $this->state = 0;
        }

        DB::beginTransaction();
        try{
            $this->save();
            DB::commit();
            return true;
        }catch (\Exception $ex){
            DB::rollBack();
            throw new ResultException($ex->getMessage(),500);
        }
    }

    /**
     * 将Calibre转为Project
     */
    public function convertToProject(&$project) {
        if (empty($project)) {
            $project = new Project();
        }
        $project->project_id = $this->project_id;
        $project->project_name = $this->title;
        $project->description = $this->description;
        $project->project_open_state = 1;
        $project->project_cover = $this->cover;
        $project->project_author = $this->author;
        $project->project_publisher = $this->publisher;
        $project->project_date = $this->date;
        return $project;
    }

    /**
     * 查询可项目列表
     * @param int $pageIndex
     * @param int $pageSize
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getCalibreProjectList($title, $pageIndex = 1, $pageSize = 20)
    {
        if (empty($title)) {
            return DB::table('calibre')->select(['*'])->orderBy('calibre_id', 'DESC')
                ->paginate($pageSize, ['*'], 'page', $pageIndex);
        } else {
            return DB::table('calibre')->select(['*'])->where("title", "like", "%".$title."%")->orderBy('calibre_id', 'DESC')
                ->paginate($pageSize, ['*'], 'page', $pageIndex);
        }
    }
}
