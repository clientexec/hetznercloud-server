<?php

require_once 'Hetznercloud.class.php';

class PluginHetznercloud extends ServerPlugin
{
    public $features = [
        'packageName' => true,
        'testConnection' => true,
        'showNameservers' => false,
        'directlink' => true
    ];

    public $api;

    public function setup($args)
    {
        $this->api = new Hetznercloud(
            $args['server']['variables']['plugin_hetznercloud_API_Key'],
        );
    }

    public function getVariables()
    {
        $variables = [
            lang("Name") => [
                "type" => "hidden",
                "description" => "Used by CE to show plugin - must match how you call the action function names",
                "value" => "Hetznercloud"
            ],
            lang("Description") => [
                "type" => "hidden",
                "description" => lang("Description viewable by admin in server settings"),
                "value" => lang("Hetznercloud control panel integration")
            ],
            lang("API Key") => [
                "type" => "text",
                "description" => lang("API Key"),
                "value" => ""
            ],
            lang("VM Password Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the root password."),
                "value" => ""
            ],
            lang("VM Rescue Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the rescue password."),
                "value" => ""
            ],
            lang("VM Hostname Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the VM hostname."),
                "value" => ""
            ],
            lang("VM MainIp Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the Main IPv4 Address."),
                "value" => ""
            ],
            lang("VM IPv6 Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the IPv6 Address."),
                "value" => ""
            ],
            lang("VM Operating System Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the VM Operating System."),
                "value" => ""
            ],
            lang("VM Location Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the Location/Region"),
                "value" => ""
            ],
            lang("VM SSHKey Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the user specified SSH Key"),
                "value" => ""
            ],
            lang("VM Userdata Custom Field") => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the userdata"),
                "value" => ""
            ],
            lang("Actions") => [
                "type" => "hidden",
                "description" => lang("Current actions that are active for this plugin per server"),
                "value" => "Create,Delete,Suspend,UnSuspend,Reset,Reboot,Boot,Shutdown,Rebuild,RescueOn,RescueOff,Changepass,BackupOn,BackupOff"
            ],
            lang('Registered Actions For Customer') => [
                "type" => "hidden",
                "description" => lang("Current actions that are active for this plugin per server for customers"),
                "value" => "Reset,Reboot,Boot,Shutdown,Rebuild,RescueOn,RescueOff,Changepass"
            ],
            lang("reseller") => [
                "type" => "hidden",
                "description" => lang("Whether this server plugin can set reseller accounts"),
                "value" => "0",
            ],
            lang("package_addons") => [
                "type" => "hidden",
                "description" => lang("Supported signup addons variables"),
                "value" => "",
            ],
            lang('package_vars') => [
                'type' => 'hidden',
                'description' => lang('Whether package settings are set'),
                'value' => '0',
            ],
            lang('package_vars_values') => [
                'type'        => 'hidden',
                'description' => lang('VM account parameters'),
                'value'       => array(
                    'plan' => array(
                        'type'        => 'dropdown',
                        'multiple'    => false,
                        'getValues'   => 'getPlans',
                        'label'       => lang('Plan'),
                        'description' => '',
                        'value'       => '',
                    ),
                ),
            ],
        ];

        return $variables;
    }


    public function getPlans()
    {
        $plans = [];
        $dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'json';
        $hideName = array('.','..','.DS_Store');
        $plans[0] = lang('-- Select VPS Plan --');
        if (file_exists($dir.'/plans.json')) {
            $ServerTypeGetAll = json_decode(file_get_contents($dir.'/plans.json'), true);
            foreach ($ServerTypeGetAll['server_types'] as $ServerTypeAll) {
                $plans[$ServerTypeAll['name']] = $ServerTypeAll['description'].'( '.$ServerTypeAll['cores'].' vCPU, '.$ServerTypeAll['memory'].' GB RAM, '.$ServerTypeAll['disk'].' GB HDD)';
            }
        }
        return $plans;
    }

    public function validateCredentials($args)
    {
    }

    public function doUpdate($args)
    {
    }


    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->DeleteServer($args['package']['ServerAcctProperties']);
        $userPackage = new UserPackage($args['package']['id']);
        $userPackage->setCustomField('Server Acct Properties', '');
        $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_Password_Custom_Field'], "", CUSTOM_FIELDS_FOR_PACKAGE);
        $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_IPv6_Custom_Field'], "", CUSTOM_FIELDS_FOR_PACKAGE);
        $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_MainIp_Custom_Field'], "", CUSTOM_FIELDS_FOR_PACKAGE);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been deleted.';
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerPowerOff($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been suspended.';
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerPowerOn($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been unsuspended.';
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $this->setup($args);

        $actions = [];
        if ($args['package']['ServerAcctProperties'] == '') {
            $actions[] = 'Create';
        } else {
            $foundServer = false;
            $servers = $this->api->serverGet($args['package']['ServerAcctProperties']);
            if ($servers['server']['status']) {
                $foundServer = true;
                if ($servers['server']['status'] == 'running') {
                    $actions[] = 'Suspend';
                    $actions[] = 'Reboot';
                    $actions[] = 'Reset';
                    $actions[] = 'Shutdown';
                    $actions[] = 'Rebuild';
                    $actions[] = 'Changepass';
                } else {
                    $actions[] = 'UnSuspend';
                    $actions[] = 'Boot';
                }
                if ($servers['server']['rescue_enabled']) {
                    $actions[] = 'RescueOff';
                } else {
                    $actions[] = 'RescueOn';
                }
                if ($servers['server']['backup_window']) {
                    $actions[] = 'BackupOff';
                } else {
                    $actions[] = 'BackupOn';
                }
                $actions[] = 'Delete';
            }

            if ($foundServer == false) {
                $actions[] = 'Create';
            }
        }

        return $actions;
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->create($args);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been created.';
    }

    public function create($args)
    {
        $this->setup($args);
        $userPackage = new UserPackage($args['package']['id']);

        $ssh_key = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_SSHKey_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $user_data = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Userdata_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $location = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Location_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $OsImage = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Operating_System_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $hostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $options = array();
        $options["name"] = $hostname;
        $options["server_type"] =  $args['package']['variables']['plan'];
        $options["location"] = $location;
        $options["image"] = $OsImage;
        $options["start_after_create"] = true;

        if ($ssh_key) {
            $SSHKey = $this->api->CreateSSHKey($hostname, $ssh_key);
            if ($SSHKey['ssh_key']['id']) {
                $options["ssh_keys"] = array($SSHKey['ssh_key']['id']);
            }
        }

        if (!empty($user_data)) {
            $options["user_data"] = $user_data;
        }

        $serverId = $this->api->CreateVM($options);
        if ($serverId['server']['id']) {
            $userPackage->setCustomField('Server Acct Properties', $serverId['server']['id']);
            $foundIp = false;
            while ($foundIp == false) {
                if ($serverId['server']['public_net']['ipv4']['ip'] != '0.0.0.0') {
                    $userPackage->setCustomField('IP Address', $serverId['server']['public_net']['ipv4']['ip']);
                    $userPackage->setCustomField('Shared', 0);
                    $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_Password_Custom_Field'], $serverId['root_password'], CUSTOM_FIELDS_FOR_PACKAGE);
                    $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_IPv6_Custom_Field'], $serverId['server']['public_net']['ipv6']['ip'], CUSTOM_FIELDS_FOR_PACKAGE);
                    $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_MainIp_Custom_Field'], $serverId['server']['public_net']['ipv4']['ip'], CUSTOM_FIELDS_FOR_PACKAGE);
                    $foundIp = true;
                    break;
                } else {
                    CE_Lib::log(4, "Sleeping for four seconds...");
                    sleep(4);
                }
            }
        }
    }

    public function doRebuild($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $this->setup($args);
        $osname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Operating_System_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $Data = $this->api->ServerRebuild($args['package']['ServerAcctProperties'], $osname);
        if ($Data['root_password']) {
            $userPackage->setCustomField($args['server']['variables']['plugin_linode_VM_Password_Custom_Field'], $Data['root_password'], CUSTOM_FIELDS_FOR_PACKAGE);
        }
        return $vmHostname . ' has been reinstalled.';
    }

    public function doBackupOff($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $this->setup($args);
          $Data = $this->api->ServerDisableBackup($args['package']['ServerAcctProperties']);
        if ($Data['action']['id']) {
            return $vmHostname . ' backup has been disabled';
        } else {
            return $vmHostname . ' an error occured during backup disable';
        }
    }

    public function doBackupOn($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $this->setup($args);
          $Data = $this->api->ServerEnableBackup($args['package']['ServerAcctProperties']);
        if ($Data['action']['id']) {
            return $vmHostname . ' backup has been enabled';
        } else {
            return $vmHostname . ' an error occured during backup enable';
        }
    }

    public function doRescueOff($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $this->setup($args);
        $Data = $this->api->ServerDisableRescue($args['package']['ServerAcctProperties']);
        if ($Data['action']['id']) {
            $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_Rescue_Custom_Field'], "", CUSTOM_FIELDS_FOR_PACKAGE);
            return $vmHostname . ' rescue mode has been disabled.';
        } else {
            return $vmHostname . ' an error occured during rescue mode disable.';
        }
    }

    public function doRescueOn($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $this->setup($args);
          $Data = $this->api->ServerEnableRescue($args['package']['ServerAcctProperties'], "linux64");
        if ($Data['root_password']) {
            $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_Rescue_Custom_Field'], $Data['root_password'], CUSTOM_FIELDS_FOR_PACKAGE);
            return $vmHostname . ' has been booted into rescue mode.';
        } else {
            return $vmHostname . ' has not booted into rescue mode as an error occured';
        }
    }

    public function doChangepass($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $Data = $this->api->ServerPasswordReset($args['package']['ServerAcctProperties']);
        if ($Data['root_password']) {
            $userPackage->setCustomField($args['server']['variables']['plugin_hetznercloud_VM_Password_Custom_Field'], $Data['root_password'], CUSTOM_FIELDS_FOR_PACKAGE);
        }
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' password has been changed.';
    }

    public function doReset($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerReset($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been reseted (Hard Reboot).';
    }
    public function doReboot($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerSoftReboot($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been rebooted.';
    }

    public function doBoot($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerPowerON($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been booted.';
    }

    public function doShutdown($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $this->api->ServerPowerOff($args['package']['ServerAcctProperties']);
        $vmHostname = $userPackage->getCustomField($args['server']['variables']['plugin_hetznercloud_VM_Hostname_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        return $vmHostname . ' has been shutdown.';
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to hetznercloud');
        $this->setup($args);
        $response = $this->api->LocationGetAll();
        if (!is_array($response)) {
            throw new CE_Exception($response);
        }
    }

    public function getDirectLink($userPackage, $getRealLink = true, $fromAdmin = false, $isReseller = false)
    {
        $linkText = $this->user->lang('Web Console');
        $args = $this->buildParams($userPackage);
        $this->setup($args);
        $VnConsole = $this->api->ServerRequestConsole($args['package']['ServerAcctProperties']);
        $b64Data = base64_encode('host=' .$VnConsole["wss_url"].'_&_password='.$VnConsole["password"]);

        if ($fromAdmin) {
            return [
                'cmd' => 'panellogin',
                'label' => $linkText
            ];
        } elseif ($getRealLink) {
            $url = '../plugins/server/hetznercloud/console.php?tokens=' . $b64Data;
            return array(
                'fa' => 'fa fa-user fa-fw',
                'link' => $url,
                'rawlink' => $linkText,
                'form' => ''
            );
        } else {
            $link = 'plugins/server/hetznercloud/console.php?tokens='.$b64Data;

            return [
                'fa' => 'fa fa-user fa-fw',
                'link' => $link,
                'text' => $linkText,
                'form' => ''
            ];
        }
    }

    public function dopanellogin($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $response = $this->getDirectLink($userPackage);
        return $response['rawlink'];
    }
}
