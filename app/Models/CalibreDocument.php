<?php

namespace SmartWiki\Models;

use DB;
use Cache;
use SmartWiki\Exceptions\DataNullException;
use SmartWiki\Exceptions\ResultException;

/**
 * SmartWiki\Models\CalibreDocument
 * @property integer $calibre_doc_id
 * @property integer $calibre_id
 * @property string $calibre_url
 * @property string $calibre_html
 * @property integer $doc_id
 * @property string $doc_name
 * @property integer $parent_id
 * @property string $doc_content
 * @property string $create_time
 * @method static \Illuminate\Database\Query\Builder|CalibreDocument whereDocId($value)
 * @method static \Illuminate\Database\Query\Builder|CalibreDocument whereDocName($value)
 * @method static \Illuminate\Database\Query\Builder|CalibreDocument whereCalibreId($value)
 * @method static \Illuminate\Database\Query\Builder|CalibreDocument whereCalibreUrl($value)
 * @mixin \Eloquent
 */
class CalibreDocument extends ModelBase
{
    protected $table = 'calibre_doc';
    protected $primaryKey = 'calibre_doc_id';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $guarded = ['calibre_doc_id'];

    public $timestamps = false;

    /**
     * 删除项目以及项目相关的文档
     * @param $calibre_id
     * @return bool
     * @throws DataNullException|ResultException
     */
    public static function deleteDocByCalibreId($calibre_id)
    {

        DB::beginTransaction();
        try {
            $documents = CalibreDocument::whereCalibreId($calibre_id)->get();
            foreach ($documents as $document) {
                $document->delete();
            }
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
}
