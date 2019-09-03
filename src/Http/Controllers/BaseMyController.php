<?php
namespace gifary\basecrud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class BaseMyController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $data;

    protected function sendResponse($data, $message='success',$code=JsonResponse::HTTP_OK)
    {
        $data = array(
            'code'      => $code,
            'message'   => $message,
            'data'      => $data
        );

        return response()->json($data,$code);
    }

    /**
     * @param Model $model
     */
    protected function beforeDelete(Model $model)
    {
    }


    /**
     * @param Model $model
     * @param Request $request
     */
    protected function beforeUpdate(Model $model, Request $request)
    {
        if(empty($this->whiteListUpdate())){
            $this->data = $request->all();
        }else{
            $this->data = $request->only($this->whiteListUpdate());
        }
    }

    /**
     * @param Request $request
     */
    protected function beforeSave(Request $request)
    {
        if(empty($this->whiteListCreate())){
            $this->data = $request->all();
        }else{
            $this->data = $request->only($this->whiteListCreate());
        }
    }

    /**
     * @param Model $model
     * @param Request $request
     */
    protected function afterSave(Model $model, Request $request)
    {
    }


    /**
     * @param Model $model
     * @param Request $request
     */
    protected function afterUpdate(Model $model, Request $request)
    {
    }

    /**
     * @param Model $model
     */
    protected function afterDelete(Model $model)
    {
    }


    /**
     * @param Request $request
     * @return bool
     */
    protected function validateBeforeSave(Request $request):bool
    {
        return true;
    }


    /**
     * @param Request $request
     * @return bool
     */
    protected function validateBeforeUpdate(Request $request,$id):bool
    {
        return true;
    }

    protected function validateFormBeforeSave(Request $request)
    {
    }

    protected function validateFormBeforeUpdate(Request $request,$id)
    {
    }

    protected function validateFormBeforeDelete($id)
    {
    }

    /**
     * @param $data
     * @param string $message
     * @return JsonResponse
     */
    protected function sendResponseSuccess($data, $message="Success"):JsonResponse
    {
        return $this->sendResponse($data,$message);
    }

    /**
     * @param $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function sendValidateErrorInput($data, $message='error', $code=JsonResponse::HTTP_BAD_REQUEST):JsonResponse
    {
        return $this->sendResponse($data,$message,$code);
    }

    /**
     * @return array
     */
    protected function whiteListCreate()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function whiteListUpdate()
    {
        return [];
    }

    protected function abortError($code,$message,$header=array())
    {
        abort($code,$message,$header);
    }
}
