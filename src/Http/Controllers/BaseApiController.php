<?php
namespace gifary\basecrud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BaseApiController  extends BaseMyController
{
    /**
     * @var Model
     */
    protected $model;

    protected $errors;

    protected $data;

    protected $with=[];

    protected $query;

    /**
     * How to use this function
     * params at URL ex base_url?desc=...
     * desc | boolean
     * sortBy | string (column name)
     * search | string (text for searching)
     * field_search | string (multiple column from table separated with ,) ex title,description
     *
     * How to use paginate. Add this as parameter
     * page | integer
     * limit | integer
     *
     * How to filter data
     * base_url?{column_name}={value to filter}
     * Example for grater than equal {column_name}[gte]={value to filter}
     * Example for grater than {column_name}[gt]={value to filter}
     * Example for less than equal {column_name}[lte]={value to filter}
     * Example for less than  {column_name}[lt]={value to filter}
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request) :JsonResponse
    {
        $data = new $this->model;

        if($request->has('with')){
            $data = $data->with(explode(",",$request->with));
        }

        if($request->has('column')){
            $col = explode(",",$request->column);

            if($request->has('groupBy')){
                array_push($col,DB::raw('COUNT(*) as total'));
                $data = $data->select($col);
            }else{
                $data = $data->select($col);
            }
        }

        if($request->has('count')){
            $data = $data->select(DB::raw("COUNT(*) as total"));
        }

        if($request->has('sum')){
            $sums = explode(",",$request->sum);
            $query=[];
            foreach ($sums as $sum){
                array_push($query,DB::raw("SUM($sum) as total_$sum"));
            }

            $data = $data->select($query);
        }

        if($request->has('desc') && $request->has('sortBy') && $request->desc!='' && $request->sortBy!='' && $request->desc!='undefined'){
            if($request->desc=='true'){
                $data = $data->orderByDesc($request->sortBy);
            }else{
                $data = $data->orderBy($request->sortBy);
            }
        }

        if($request->has('search')){
            $field_search = $request->field_search;
            $text_search = $request->search;
            $data = $data->where(function ($q) use($field_search,$text_search){
                $fs = explode(",",$field_search);
                foreach ($fs as $search){
                    if(strpos($search,".")){
                        $split_text = explode(".",$search);
                        $tbl = $split_text[0];
                        $column = $split_text[1];

                        $q = $q->orWhereHas($tbl,function ($query)use($column,$text_search){
                            $query->where($column,"LIKE","%$text_search%");
                        });
                    }else{
                        $q = $q->orWhere($search,"LIKE","%$text_search%");
                    }
                }
            });
        }

        if($request->has('filter')){
            foreach ($request->filter as $condition => $values){
                if(is_array($values)){
                    foreach ($values as $key => $value){
                        try{
                            if($key=='lt'){
                                $data = $data->where($condition,'<',$value);
                            }else if($key=='lte'){
                                $data = $data->where($condition,'<=',$value);
                            }else if($key=='gt'){
                                $data = $data->where($condition,'>',$value);
                            }else if($key=='gte'){
                                $data = $data->where($condition,'>=',$value);
                            }else if($key=='in'){
                                $data = $data->whereIn($condition,explode(",",$value));
                            }else if($key=='notIn'){
                                $data = $data->whereNotIn($key,explode(",",$value));
                            }else{
                                if($value=='null'){
                                    $data = $data->whereNull($condition);
                                }else{
                                    $data = $data->where($condition,$value);
                                }

                            }
                        }catch (\Exception $exception){
                            Log::error('error column',['message'=>$exception->getMessage()]);
                        }
                    }
                }else{
                    try{
                        if(strpos($condition,".")){
                            $split_text = explode(".",$condition);

                            $tbl = $split_text[0];
                            $column = $split_text[1];
                            $data = $data->whereHas($tbl,function ($query)use($column,$values){
                                $query->where($column,"LIKE","%$values%");
                            });
                        }else{
                            if($values=='null'){
                                $data = $data->whereNull($condition);
                            }else{
                                $data = $data->where($condition,$values);
                            }
                        }
                    }catch (\Exception $exception){
                        Log::error('error column',['message'=>$exception->getMessage()]);
                    }
                }

            }
        }

        if($request->has('groupBy')){
            $group = explode(",",$request->groupBy);

            $data = $data->groupBy($group);
        }

        if($request->has('sum')|| $request->has('count')){
            $data = $data->get();
        }else{
            if($request->has('limit') && $request->limit >0){
                $limit=$request->limit;
                $data = $data->paginate($limit);
            }else{
                $data = $data->paginate($this->model::count());
            }
        }


        return $this->sendResponseSuccess($data,"Success get list data");
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if(!$this->validateBeforeSave($request)){
            return $this->sendValidateErrorInput($this->errors);
        }

        $this->beforeSave($request);

        $model = $this->model::create($this->data);

        $this->afterSave($model,$request);

        return $this->sendResponseSuccess($model);
    }


    protected function getItem($id)
    {
        if(empty($this->with)){
            $model = $this->model::find($id);
        }else{
            $model =  $this->model::with($this->with)->first();
        }

        return $model;
    }


    public function edit(Request $request,$id):JsonResponse
    {
        if($request->has('with')){
            $this->with = explode(",",$request->with);
        }
        $data = $this->getItem($id);
        if(!empty($data)){
            return $this->sendResponse($data);
        }else{
            return $this->sendValidateErrorInput(null,'data not found');
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function update(Request $request, $id) : JsonResponse
    {
        if(!$this->validateBeforeUpdate($request,$id)){
            return $this->sendValidateErrorInput($this->errors);
        }

        $model = $this->getItem($id);

        $this->beforeUpdate($model,$request);

        $model->update($this->data);

        $this->afterUpdate($model,$request);

        return $this->sendResponseSuccess($model,'Success edited');
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy($id) :JsonResponse
    {
        $model = $this->getItem($id);
        $this->beforeDelete($model);

        $model->delete();
        $this->afterDelete($model);

        return $this->sendResponseSuccess($model,'Success deleted');
    }
}
