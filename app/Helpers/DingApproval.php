<?php

namespace App\Helpers;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Throwable;

class DingApproval
{
    // 审批状态，无
    const PROCESS_STATUS_NONE = 'NONE';

    // 审批状态，新创建
    const PROCESS_STATUS_NEW = 'NEW';

    // 审批状态，审批中
    const PROCESS_STATUS_RUNNING = 'RUNNING';

    // 审批状态，被终止
    const PROCESS_STATUS_TERMINATED = 'TERMINATED';

    // 审批状态，完成
    const PROCESS_STATUS_COMPLETED = 'COMPLETED';

    // 审批状态，取消
    const PROCESS_STATUS_CANCELED = 'CANCELED';

    const START = 'START';

    const FINISH = 'FINISH';

    const START_FINISH = 'START_FINISH';

    /**
     * 审批结果：同意.
     *
     * @var string
     */
    const AGREE = 'agree';

    /**
     * 审批结果：拒绝.
     *
     * @var string
     */
    const REFUSE = 'refuse';

    /**
     * 应用标识.
     *
     * @var string
     */
    private $agentId = '301878439';

    /**
     * 审批流的唯一码.
     *
     * @var string
     */
    private $processCode = 'PROC-7E66B526-87C8-42BA-9CF0-6A3B3D9E60A6';

    /**
     * @var Client
     */
    private $client;

    /**
     * 请求地址.
     *
     * @var string
     */
    private $url = 'https://oapi.dingtalk.com';

    /**
     * 调用服务端API的应用凭证.
     *
     * @var string
     */
    private $accessToken;

    /**
     * 审批实例ID.
     *
     * @var string
     */
    private $processInstanceId;

    /**
     * @var string
     */
    private $processResult;

    /**
     * @var string
     */
    private $processStatus;

    /**
     * @var array
     */
    private $operationRecords;

    /**
     * @var string
     */
    private $errorMessage;

    public function __construct($processCode = null)
    {
        if (!is_null($processCode)) {
            $this->processCode = $processCode;
        }
        $this->client = new Client(['base_uri' => $this->url, 'verify' => false]);
        $this->accessToken = (new DingToken())->getAccessToken();
    }

    /**
     * 发起审批实例.
     *
     * @param $config
     *
     * @return bool
     */
    public function createProcessInstance($config)
    {
        $rules = [
            'originator_user_id' => 'required|string',
            'dept_id' => 'required|integer',
            'approvers' => 'required|string',
            'form_component_values' => 'required|array',
            'cc_list' => 'nullable|string',
            'cc_position' => 'nullable|string',
        ];
        $customAttributes = [
            'originator_user_id' => '审批实例发起人',
            'dept_id' => '发起人所在的部门',
            'approvers' => '审批人列表',
            'form_component_values' => '审批流表单',
            'cc_list' => '抄送人',
            'cc_position' => '抄送节点',
        ];
        $validator = validator($config, $rules, [], $customAttributes);
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }

        $body = [
            'agent_id' => $this->agentId,
            'process_code' => $this->processCode,
            'originator_user_id' => $config['originator_user_id'],
            'dept_id' => $config['dept_id'],
            'approvers' => $config['approvers'],
            'form_component_values' => $config['form_component_values'],
        ];
        if (isset($config['cc_list'])) {
            $body['cc_list'] = $config['cc_list'];
        }
        if (isset($config['cc_position'])) {
            $body['cc_position'] = $config['cc_position'];
        }

        try {
            $response = $this->client->request('POST', 'topapi/processinstance/create', [
                RequestOptions::QUERY => ['access_token' => $this->accessToken],
                RequestOptions::JSON => $body,
            ]);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents(), true);
                $this->errorMessage = $json['errmsg'];
                if (0 == $json['errcode']) {
                    $this->processInstanceId = $json['process_instance_id'];

                    return true;
                }
            }
        } catch (GuzzleException | Exception $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        return false;
    }

    /**
     * 获取审批实例详情.
     *
     * @param string $processInstanceId
     *
     * @return bool
     */
    public function getProcessInstance($processInstanceId)
    {
        try {
            $response = $this->client->request('POST', 'topapi/processinstance/get', [
                RequestOptions::QUERY => ['access_token' => $this->accessToken],
                RequestOptions::BODY => json_encode(['process_instance_id' => $processInstanceId]),
            ]);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents(), true);
                $this->errorMessage = $json['errmsg'];
                if (0 == $json['errcode']) {
                    $this->processStatus = $json['process_instance']['status'];
                    $this->processResult = $json['process_instance']['result'];
                    $this->operationRecords = $json['process_instance']['operation_records'];

                    return true;
                }
            }
        } catch (Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return bool
     */
    public function isAgree()
    {
        return self::PROCESS_STATUS_COMPLETED === strtoupper($this->processStatus)
            && self::AGREE === strtolower($this->processResult);
    }

    /**
     * @return bool
     */
    public function isRefuse()
    {
        return self::PROCESS_STATUS_COMPLETED === strtoupper($this->processStatus)
            && self::AGREE === strtolower($this->processResult);
    }

    /**
     * @return string
     */
    public function getProcessInstanceId()
    {
        return $this->processInstanceId;
    }

    /**
     * @return string
     */
    public function getProcessStatus()
    {
        return strtoupper($this->processStatus);
    }

    /**
     * @return array
     */
    public function getOperationRecords()
    {
        return $this->operationRecords;
    }
}
