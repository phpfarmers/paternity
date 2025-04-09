<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class ApiException extends Exception
{
    /**
     * 自定义异常数据
     *
     * @var mixed
     */
    protected $customData;

    /**
     * 构造
     *
     * @param integer $code
     * @param string $message
     * @param mixed $customData
     */
    public function __construct(int $code = 0, string $message = "", $customData = [])
    {
        parent::__construct($message, $code);
        $this->customData = $customData;
    }

    /**
     * 生产环境，把错误发送到钉钉群
     */
    public function report()
    {
        if (!empty($this->customData)) {
            Log::warning($this->getMessage(), [
                'customData' => $this->customData,
            ]);
        }
    }

    /**
     * 响应 json
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render()
    {
        $response = [];
        $response['code'] = $this->code;
        $response['message'] = $this->message;
        $response['data'] = $this->customData;
        return response()->json($response);
    }
}
