<?php

namespace gifary\basecrud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Yajra\DataTables\DataTables;

class BaseCrudController extends BaseMyController
{
    /**
     * @var string
     */
    public $routePath = null;

    /**
     * @var string
     */
    public $prefix = null;


    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var array
     */
    protected $with=[];

    /**
     * @var
     */
    protected $query;


    /**
     * @param Request $request
     * @return View
     */
    public function index(Request $request) : View
    {
        $data = $this->dataForIndex();
        return view($this->prefix.'.index',compact('data'));
    }

    /**
     * Function for render html at data table
     * @return array
     */
    protected function rawColumns() : array
    {
        return [];
    }

    /**
     * @return array
     */
    protected function filterData() : array
    {

    }


    /**
     * @param DataTables $datatables
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(Datatables $datatables, Request $request)
    {
        try{
            $data = new $this->model;

            if($request->has('filter'))
            {
                foreach ($request->filter as $key => $value){
                    if(is_array($value)){
                        foreach ($value as $key2 => $v){
                            try{
                                if($key2=='lt'){
                                    $data = $data->where($key,'<',$v);
                                }else if($key2=='lte'){
                                    $data = $data->where($key,'<=',$v);
                                }else if($key2=='gt'){
                                    $data = $data->where($key,'>',$v);
                                }else if($key2=='gte'){
                                    $data = $data->where($key,'>=',$v);
                                }else if($key2=='in'){
                                    $data = $data->whereIn($key,explode(",",$v));
                                }else if($key2=='notIn'){
                                    $data = $data->whereNotIn($key,explode(",",$v));
                                }else{
                                    if($value=='null'){
                                        $data = $data->whereNull($key2);
                                    }else{
                                        $data = $data->where($key,$v);
                                    }
                                }
                            }catch (\Exception $exception){
                                Log::error('error column',['message'=>$exception->getMessage()]);
                            }
                        }
                    }else{
                        try{
                            if(strpos($key,".")){
                                $split_text = explode(".",$key);

                                $tbl = $split_text[0];
                                $column = $split_text[1];
                                $data = $data->whereHas($tbl,function ($query)use($column,$value){
                                    $query->where($column,"LIKE","%$value%");
                                });
                            }else{
                                $data = $data->where($key,$value);
                            }
                        }catch (\Exception $exception){
                            Log::error('error column',['message'=>$exception->getMessage()]);
                        }
                    }

                }
            }

            $data = $data->get();

            $datatables = $datatables->collection($data)
                ->addColumn('action', function (Model $model){
                    //return $this->prefix.'.button_action';
                    $id= $model->id;
                    return view($this->prefix.'.button_action', compact('model','id'));
                })
                ->rawColumns($this->rawColumns());

            if(!empty($this->renderAdditionalColumnTable())){
                foreach ($this->renderAdditionalColumnTable() as $column)
                {
                    $datatables = $datatables->addColumn($column['name'],$column['function']);
                }
            }

            if(!empty($this->renderEditColumnTable())){
                foreach ($this->renderEditColumnTable() as $column)
                {
                    $datatables = $datatables->editColumn($column['name'],$column['function']);
                }
            }


            return $datatables->make(true);

        }catch (\Exception $e){
            $this->abortError(500,$e->getMessage());
        }
    }

    /**
     * function for add column at data table
     * example array
     * array( name=>"", function=>"fill this with function")
     * @return array
     */
    protected function renderAdditionalColumnTable()
    {
        return [];
    }

    /**
     * function for edit column at data table
     * example array
     * array( name=>"", function=>"fill this with function")
     * @return array
     */
    protected function renderEditColumnTable()
    {
        return [];
    }


    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create() : View
    {
        $data = $this->dataForCreate();
        return view($this->prefix.'.create', compact('data'));
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function store(Request $request)
    {
        $this->validateFormBeforeSave($request);

        $this->beforeSave($request);

        $model = $this->model::create($this->data);

        $this->afterSave($model,$request);

        return redirect()->route($this->routePath)->with('message', 'Success input.');
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id) : View
    {
        $data = $this->dataForShow();
        $model = $this->getItem($id);
        return view($this->prefix.'.show', compact('data','model'));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id) : View
    {
        $data = $this->dataForEdit($id);
        $model = $this->getItem($id);
        return view($this->prefix.'.edit', compact('data','model'));
    }

    /**
     * @param Request $request
     * @param $id
     * @return $this
     */
    public function update(Request $request, $id)
    {
        $this->validateFormBeforeUpdate($request,$id);

        $model = $this->getItem($id);

        $this->beforeUpdate($model,$request);

        $model->update($this->data);

        $this->afterUpdate($model,$request);

        return redirect()->route($this->routePath)->with('message', 'Success update.');
    }

    /**
     * @param $id
     * @return $this
     */
    public function destroy($id)
    {
        try{
            $model = $this->getItem($id);
            $this->validateFormBeforeDelete($id);
            $this->beforeDelete($model);

            $model->delete();
            $this->afterDelete($model);

            return redirect()->route($this->routePath)->with('message', 'Success delete.');
        }catch (\Exception $e){
            $this->abortError(500,$e->getMessage());
        }
    }


    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     */
    protected function getItem($id)
    {
        try{
            if(empty($this->with)){
                $model = $this->model::findOrFail($id);
            }else{
                $model =  $this->model::with($this->with)->where('id',$id)->first();
            }
            return $model;
        }catch (\Exception $e){
            $this->abortError(500,$e->getMessage());
        }
    }

    /**
     * function for binding data when call index blade
     * @return array
     */
    public function dataForIndex() : array
    {
        return [];
    }

    /**
     * function for binding data when call edit blade
     * @return array
     */
    public function dataForEdit($id) : array
    {
        return [];
    }

    /**
     * function for binding data when call show blade
     * @return array
     */
    public function dataForShow() : array
    {
        return [];
    }

    /**
     * function for binding data when call create blade
     * @return array
     */
    public function dataForCreate() : array
    {
        return [];
    }
}
