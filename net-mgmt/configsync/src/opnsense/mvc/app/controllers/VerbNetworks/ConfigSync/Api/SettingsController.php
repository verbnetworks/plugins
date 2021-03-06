<?php

/*
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace VerbNetworks\ConfigSync\Api;

use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiControllerBase;
use \VerbNetworks\ConfigSync\ConfigSync;

class SettingsController extends ApiControllerBase
{
    
    public function getAction()
    {
        $response = array();
        if ($this->request->isGet()) {
            $model_ConfigSync = new ConfigSync();
            $response['ConfigSync'] = $model_ConfigSync->getNodes();
            $response['ConfigSync']['settings']['system_host_id'] = $this->getHostid();
        }
        return $response;
    }

    public function setAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");
        
        if ($this->request->isPost()) {
            $model_ConfigSync = new ConfigSync();
            $model_ConfigSync->setNodes($this->request->getPost('ConfigSync'));
            $response["validations"] = $this->unpackValidationMessages($model_ConfigSync, 'ConfigSync');
            
            if (0 == count($response["validations"])) {
                $model_ConfigSync->serializeToConfig();
                Config::getInstance()->save();
                $response["status"] = "success";
                $response["message"] = "Configuration saved.";
                unset($response["validations"]);
            }
        }
        return $response;
    }
    
    public function testAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");
        
        if ($this->request->isPost()) {
            $model_ConfigSync = new ConfigSync();
            $model_ConfigSync->setNodes($this->request->getPost('ConfigSync'));
            $response["validations"] = $this->unpackValidationMessages($model_ConfigSync, 'ConfigSync');
            
            if (0 == count($response["validations"])) {
                $data = $this->request->getPost('ConfigSync');
                $backend = new Backend();
                
                if ('aws_s3' == $data['settings']['provider']) {
                    $configd_run = sprintf(
                        'configsync aws_s3_test_parameters --key_id=%s --key_secret=%s --bucket=%s --path_prefix=%s',
                        escapeshellarg($data['settings']['provider_key']),
                        escapeshellarg($data['settings']['provider_secret']),
                        escapeshellarg($data['settings']['storage_bucket']),
                        escapeshellarg($data['settings']['storage_path_prefix'])
                    );
                    $response = json_decode(trim($backend->configdRun($configd_run)), true);
                    if (empty($response)) {
                        $response = array(
                            "status" => "fail",
                            "message" => "Error calling configsync aws_s3_test_parameters via configd"
                        );
                    }
                } else {
                    $response["message"] = "Provider not supported";
                }
            } else {
                $response["message"] = "Invalid configuration data provided for testing";
            }
        }
        
        if (isset($response['data'])) {
            if (is_string($response["data"])) {
                $response["message"] = $response["message"] . ": " . $response["data"];
            } else {
                $response["message"] = $response["message"] . ": " . json_encode($response["data"]);
            }
        }
        
        return $response;
    }
    
    private function getHostid()
    {
        if (file_exists('/etc/hostid')) {
            return trim(file_get_contents('/etc/hostid'));
        }
        return '00000000-0000-0000-0000-000000000000';
    }
    
    private function unpackValidationMessages($model, $namespace)
    {
        $response = array();
        $validation_messages = $model->performValidation();
        foreach ($validation_messages as $field => $message) {
            $response[$namespace.'.'.$message->getField()] = $message->getMessage();
        }
        return $response;
    }
}
