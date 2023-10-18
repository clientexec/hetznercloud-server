<?php
class Hetznercloud
{
    private $curl;
    private $curlOptions = array();
    private $httpHeader = array();
    private $baseUrl;
    private $ApiKey;

    public function __construct($ApiKey)
    {
        $this->baseUrl = rtrim('https://api.hetzner.cloud/v1/', '/');
        $this->curl = curl_init();
        $this->ApiKey = $ApiKey;
        $this->setHttpHeader('Content-Type', 'application/json');
        $this->setHttpHeader('Authorization', 'Bearer ' . $this->ApiKey);
        $this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
        $this->setCurlOption(CURLOPT_VERBOSE, false);
    }

    protected function setCurlOption($option, $value)
    {
        $this->curlOptions[$option] = $value;
    }

    protected function getCurlOption($option)
    {
        return isset($this->curlOptions[$option]) ? $this->curlOptions[$option] : null;
    }

    public function setHttpHeader($name, $value)
    {
        $this->httpHeader[] = $name . ': ' . $value;
    }

    protected function get($url)
    {
        $this->setCurlOption(CURLOPT_URL, $this->baseUrl . '/' . $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'GET');

        return $this->executeRequest();
    }

    protected function post($url, $data = null)
    {
        $this->setCurlOption(CURLOPT_URL, $this->baseUrl . '/' . $url);
        $this->setCurlOption(CURLOPT_POST, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'POST');
        if ($data) {
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }

        return $this->executeRequest();
    }

    protected function put($url, $data = null)
    {
        $this->setCurlOption(CURLOPT_URL, $this->baseUrl . '/' . $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }

        return $this->executeRequest();
    }

    protected function delete($url, $data = null)
    {
        $this->setCurlOption(CURLOPT_URL, $this->baseUrl . '/' . $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ($data) {
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }

        return $this->executeRequest();
    }

    protected function executeRequest()
    {
        $this->setCurlOption(CURLOPT_HTTPHEADER, array_values($this->httpHeader));
        curl_setopt_array($this->curl, $this->curlOptions);
        $response = curl_exec($this->curl);
        return json_decode($response, true);
        CE_Lib::log(4, 'Hetzner Cloud Response: ' . $response);
    }

    //List all Actions -
    public function actionsGetAll()
    {
        return $this->get('actions');
    }

    //Get one Action -
    public function actionGet($id)
    {
        return $this->get('actions/' . $id);
    }

    //Get All Servers -
    public function serverGetAll($page, $per_page)
    {
        return $this->get('servers?page=' . $page . '&per_page=' . $per_page);
    }

    //Get All Servers - for Snapshot
    public function serverGetAllSnap()
    {
        return $this->get('servers');
    }

    public function countElements()
    {
        return $this->get('servers') ['meta']['pagination']['total_entries'];
    }

    //Get a Server -
    public function serverGet($id)
    {
        return $this->get('servers/' . $id);
    }

    //2020.3
    public function CreateVM($JsonData)
    {
        return $this->post('servers', $JsonData);
    }

    public function VolumeCreate($size, $name, $automount, $location, $format)
    {
        return $this->post('volumes', array(
            'size' => $size,
            'name' => $name,
            'automount' => $automount,
            'location' => $location,
            'format' => $format,
        ));
    }

    //Create a Server -
    public function CreateServer($name, $servertype, $location, $image, $ssh_key, $user_data)
    {
        if ($ssh_key) {
            $SSHKey = $this->CreateSSHKey($name, $ssh_key);
            return $this->post('servers', array(
                'name' => $name,
                'server_type' => $servertype,
                'location' => $location,
                'image' => $image,
                //'ssh_keys' => [1065567], //working
                //'ssh_keys' => array(1065567), //working
                'ssh_keys' => array(
                    $SSHKey['ssh_key']['id']
                ) ,
                'user_data' => $user_data,
                'start_after_create' => true,
                'automount' => false,
            ));
        } else {
            return $this->post('servers', array(
                'name' => $name,
                'server_type' => $servertype,
                'location' => $location,
                'image' => $image,
                'user_data' => $user_data,
                'start_after_create' => true,
                'automount' => false,
            ));
        }
    }

    //Update a Server -
    public function UpdateServer($id, $name)
    {
        return $this->put('servers/' . $id, array(
            'name' => $name
        ));
    }

    //Delete a Server -
    public function DeleteServer($id)
    {
        return $this->delete('servers/' . $id);
    }

    //Get Metrics for a Server
    public function MetricsServerGet($id, $type)
    {
        return $this->get('servers/' . $id . '/metrics?type=' . $type . '&start=' . date('Y-m-01') . 'T00:00:00Z&end=' . date('Y-m-d') . 'T00:00:00Z');
    }

    //BW Usange Data
    public function DatatrafficMetrics($CloudID, $type, $start, $end)
    {
        return $this->get('servers/' . $CloudID . '/metrics?type=' . $type . '&start=' . $start . '&end=' . $end);
    }

    //New Graph Style
    public function PHPTimestampToIso8601($timestamp, $utc = true)
    {
        $datequerystring = date("Y-m-d\\TH:i:sO", $timestamp);
        if ($utc) {
            $eregStr = "/([0-9]{4})-" . "([0-9]{2})-" . "([0-9]{2})" . "T" . "([0-9]{2}):" . "([0-9]{2}):" . "([0-9]{2})(\\.[0-9]*)?" . "(Z|[+\\-][0-9]{2}:?[0-9]{2})?/";
            if (preg_match($eregStr, $datequerystring, $regs)) {
                return sprintf("%04d-%02d-%02dT%02d:%02d:%02dZ", $regs[1], $regs[2], $regs[3], $regs[4], $regs[5], $regs[6]);
            }
            return false;
        }
        return $datequerystring;
    }

    public function Date2UTC($time)
    {
        $newtime = date("Y-m-d H:i:s", $time);
        $newtime01 = date("Y,m,d,H,i,s", strtotime($newtime . " -1 months"));
        return "Date.UTC(" . $newtime01 . ")";
    }

    #Server Actions#
    //Get all Actions for a Server
    public function ServerActionsGetAll($id)
    {
        return $this->get('servers/' . $id . '/actions');
    }

    //Get a specific Action for a Server
    public function ServerSpecificActionGet($id, $action_id)
    {
        return $this->get('servers/' . $id . '/actions/' . $action_id);
    }

    //Power on a Server
    public function ServerPowerON($id)
    {
        return $this->post('servers/' . $id . '/actions/poweron');
    }

    //Soft-reboot a Server
    public function ServerSoftReboot($id)
    {
        return $this->post('servers/' . $id . '/actions/reboot');
    }

    //Reset a Server
    public function ServerReset($id)
    {
        return $this->post('servers/' . $id . '/actions/reset');
    }

    //Shutdown a Server
    public function ServerShutdown($id)
    {
        return $this->post('servers/' . $id . '/actions/shutdown');
    }

    //Power off a Server
    public function ServerPowerOff($id)
    {
        return $this->post('servers/' . $id . '/actions/poweroff');
    }

    //Reset root Password of a Server
    public function ServerPasswordReset($id)
    {
        return $this->post('servers/' . $id . '/actions/reset_password');
    }

    //Enable Rescue Mode for a Server
    public function ServerEnableRescue($id, $Rescuetype)
    {
        return $this->post('servers/' . $id . '/actions/enable_rescue', ['type' => $Rescuetype]);
    }

    //Disable Rescue Mode for a Server
    public function ServerDisableRescue($id)
    {
        return $this->post('servers/' . $id . '/actions/disable_rescue');
    }

    //Create Image from a Server
    public function ServerCreateImage($id, $imagetype, $description)
    {
        return $this->post('servers/' . $id . '/actions/create_image', ['type' => $imagetype, 'description' => $description]);
    }

    //Rebuild a Server from an Image
    public function ServerRebuild($id, $Image)
    {
        return $this->post('servers/' . $id . '/actions/rebuild', ['image' => $Image]);
    }

    //Change the Type of a Server
    public function ServerChangeType($id, $server_type)
    {
        return $this->post('servers/' . $id . '/actions/change_type', ['upgrade_disk' => true, 'server_type' => $server_type]);
    }

    //Enable and Configure Backups for a Server
    public function ServerEnableBackup($id)
    {
        return $this->post('servers/' . $id . '/actions/enable_backup');
    }

    //Disable Backups for a Server
    public function ServerDisableBackup($id)
    {
        return $this->post('servers/' . $id . '/actions/disable_backup');
    }

    //Attach an ISO to a Server
    public function ServerAttachISO($id, $ISO)
    {
        return $this->post('servers/' . $id . '/actions/attach_iso', ['iso' => $ISO]);
    }

    //Detach an ISO from a Server
    public function ServerDetachISO($id)
    {
        return $this->post('servers/' . $id . '/actions/detach_iso');
    }

    //Change reverse DNS entry for this server
    public function ServerIPrDNS($id, $ip, $dns_ptr)
    {
        return $this->post('servers/' . $id . '/actions/change_dns_ptr', ['dns_ptr' => $dns_ptr, 'ip' => $ip]);
    }

    //Change protection for a Server
    public function ServerEnableProtection($id)
    {
        return $this->post('servers/' . $id . '/actions/change_protection', ['delete' => true, 'rebuild' => true]);
    }

    //Change protection for a Server
    public function ServerDisableProtection($id)
    {
        return $this->post('servers/' . $id . '/actions/change_protection', ['delete' => false, 'rebuild' => false]);
    }

    // Request Console for a Server
    public function ServerRequestConsole($id)
    {
        return $this->post('servers/' . $id . '/actions/request_console');
    }

    #Floating IPs#
    //Get all Floating IPs
    public function FloatingIPsGetAll()
    {
        return $this->get('floating_ips');
    }

    //Get a Floating IP
    public function FloatingIPsGet($id)
    {
        return $this->get('floating_ips/' . $id);
    }

    //Create a Floating IP
    public function FloatingIPCreate($id, $type)
    {
        return $this->post('floating_ips', ['type' => $type, 'server' => $id, ]);
    }

    //Update a Floating IP
    public function FloatingIPUpdate($floating_ip_id, $description)
    {
        return $this->put('floating_ips/' . $floating_ip_id, ['description' => $description, 'name' => $description]);
    }

    //Delete a Floating IP
    public function FloatingIPDelete($floating_ip_id)
    {
        return $this->delete('floating_ips/' . $floating_ip_id);
    }

    #Floating IP Actions#
    //Get all Actions for a Floating IP
    public function FloatingIPActionsGetAll($floating_ip_id)
    {
        return $this->get('floating_ips/' . $floating_ip_id . '/actions');
    }

    //Get a Actions for a Floating IP
    public function FloatingIPActionGet($floating_ip_id, $action_id)
    {
        return $this->get('floating_ips/' . $floating_ip_id . '/actions/' . $action_id);
    }

    //Assign a Floating IP to a Server
    public function FloatingIPAssignServer($floating_ip_id, $serverID)
    {
        return $this->post('floating_ips/' . $floating_ip_id . '/actions/assign', ['server' => $serverID]);
    }

    //Unassign a Floating IP
    public function FloatingIPUnassignServer($floating_ip_id)
    {
        return $this->post('floating_ips/' . $floating_ip_id . '/actions/unassign');
    }

    // Change reverse DNS entry for a Floating IP
    public function FloatingIPrDNS($floating_ip_id, $FloatingIP, $dns_ptr)
    {
        return $this->post('floating_ips/' . $floating_ip_id . '/actions/change_dns_ptr', ['dns_ptr' => $dns_ptr, 'ip' => $FloatingIP]);
    }

    //Change protection
    public function FloatingIPEnableProtection($floating_ip_id)
    {
        return $this->post('floating_ips/' . $floating_ip_id . '/actions/change_protection', ['delete' => true]);
    }

    //Change protection
    public function FloatingIPDisableProtection($floating_ip_id)
    {
        return $this->post('floating_ips/' . $floating_ip_id . '/actions/change_protection', ['delete' => false]);
    }

    public function SSHKeysGetAll()
    {
        return $this->get('ssh_keys');
    }

    public function SSHKeyGet($id)
    {
        return $this->get('ssh_keys/' . $id);
    }

    //Create an SSH key
    public function CreateSSHKey($name, $public_key)
    {
        return $this->post('ssh_keys', ['name' => $name, 'public_key' => $public_key]);
    }

    //Update an SSH key
    public function UpdateSSHKey($id, $name)
    {
        return $this->put('ssh_keys/' . $id, ['name' => $name]);
    }

    //Delete an SSH key
    public function DeleteSSHKey($id)
    {
        return $this->delete('ssh_keys/' . $id);
    }

    #Server Types#
    //Get All Server Types
    public function ServerTypeGetAll()
    {
        return $this->get('server_types');
    }

    //Get a Server Type
    public function ServerTypeGet($id)
    {
        return $this->get('server_types/' . $id);
    }

    #Locations#
    //Get all Locations
    public function LocationGetAll()
    {
        return $this->get('locations');
    }

    //Get a Location
    public function LocationGet($id)
    {
        return $this->get('locations/' . $id);
    }

    #Datacenters#
    //Get all Datacenters
    public function DatacenterGetAll()
    {
        return $this->get('datacenters');
    }

    //Get a Datacenter
    public function DatacenterGet($id)
    {
        return $this->get('datacenters/' . $id);
    }

    #Images#
    //Get all Images
    public function ImagesGetAll()
    {
        return $this->get('images');
    }

    //Get Images types
    public function ImagesGetTypes($type)
    {
        return $this->get('images?type=' . $type);
    }

    //Get Backups of a Server
    public function GetServerBackups($ServerID)
    {
        return $this->get('images?type=backup&bound_to=' . $ServerID);
    }

    //Get an Image
    public function ImageGet($id)
    {
        return $this->get('images/' . $id);
    }

    //Update an Image (Snapshot/Backups Only)
    public function ImageUpdate($id, $description, $type)
    {
        return $this->put('images/' . $id, ['description' => $description, 'type' => $type]);
    }

    //Update an Image (Snapshot/Backups Only)
    public function SnapImageUpdate($id, $description)
    {
        return $this->put('images/' . $id, ['description' => $description]);
    }

    //Delete an Image (Snapshot/Backups Only)
    public function ImageDelete($id)
    {
        return $this->delete('images/' . $id);
    }

    #Image Actions#
    //Get all Actions for an Image
    public function ImageActions($id)
    {
        return $this->get('images/' . $id . '/actions');
    }

    //Get an Action for an Image
    public function ImageAction($id, $action)
    {
        return $this->get('images/' . $id . '/actions/' . $action);
    }

    //Get an Action for an Image
    public function ImageChangeProtection($id)
    {
        return $this->post('images/' . $id . '/actions/change_protection', ['delete' => true]);
    }

    //Get an Action for an Image
    public function ImageChangeProtectionDisable($id)
    {
        return $this->post('images/' . $id . '/actions/change_protection', ['delete' => false]);
    }

    #ISOs#
    //Get all ISOs
    public function ISOsGet()
    {
        return $this->get('isos?page=1&per_page=50');
    }

    //Get an ISOs
    public function ISOGet($id)
    {
        return $this->get('isos/' . $id);
    }

    #Get all prices#
    //Get all prices
    public function Pricing()
    {
        return $this->get('pricing');
    }

    #Volumes
    //Get all Volumes
    public function VolumesGetAll()
    {
        return $this->get('volumes');
    }

    //Get a Volume
    public function VolumeGet($id)
    {
        return $this->get('volumes/' . $id);
    }

    //Create a Volume
    public function CreateVolume($size, $name, $server, $automount, $format)
    {
        return $this->post('volumes', ['size' => $size, 'name' => $name, 'automount' => $automount, 'server' => $server, 'format' => $format, ]);
    }

    //Update a Volume
    public function UpdateVolume($id, $name)
    {
        return $this->put('volumes/' . $id, ['name' => $name]);
    }

    //Delete a Volume
    public function DeleteVolume($id)
    {
        return $this->delete('volumes/' . $id);
    }

    #Volume Actions#
    //Get all Actions for a Volume
    public function VolumeActionsGetAll($id)
    {
        return $this->get('volumes/' . $id . '/actions');
    }

    //Get an Action for a Volume
    public function VolumeActionsGet($id, $action_id)
    {
        return $this->get('volumes/' . $id . '/actions' . $action_id);
    }

    //Attach Volume to a Server
    public function AttachVolume($id, $server, $automount)
    {
        return $this->post('volumes/' . $id . '/actions/attach', ['server' => $server, 'automount' => $automount]);
    }

    //Detach Volume to a Server
    public function DetachVolume($id)
    {
        return $this->post('volumes/' . $id . '/actions/detach');
    }

    //Resize a Volume
    public function ResizeVolume($id, $size)
    {
        return $this->post('volumes/' . $id . '/actions/resize', ['size' => $size]);
    }

    //Change Volume Protection
    public function ChangeVolumeProtection($id, $status)
    {
        return $this->post('volumes/' . $id . '/actions/change_protection', ['delete' => $status]);
    }

    //firewall
    public function getFirewalls()
    {
        return $this->get('firewalls');
    }

    public function getFirewallbyId($fid)
    {
        return $this->get('firewalls/' . $fid);
    }

    public function ChangefirewallName($fid, $name)
    {
        return $this->post('firewalls/' . $fid, ['name' => $name]);
    }

    public function Deletefirewall($fid)
    {
        return $this->delete('firewalls/' . $fid);
    }

    public function updateFirewallRule($fid, $ip, $protocol, $port, $type)
    {
        $ips = (($type == 'in') ? 'source_ips' : 'destination_ips');
        $data['rules'][0]['direction'] = $type;
        $data['rules'][0][$ips] = array(
            $ip
        );
        $data['rules'][0]['protocol'] = $protocol;
        $data['rules'][0]['port'] = $port;
        return $this->post('firewalls/' . $fid . '/actions/set_rules', $data);
    }

    public function detachFirewallRule($fid, $serverId)
    {
        $data['remove_from'][0]['type'] = 'server';
        $data['remove_from'][0]['server']['id'] = $serverId;
        return $this->post('firewalls/' . $fid . '/actions/remove_from_resources', $data);
    }

    public function createFirewallRule($name, $ip, $protocol, $port, $type, $serverid)
    {
        $ips = (($type == 'in') ? 'source_ips' : 'destination_ips');
        $data['name'] = $name;
        $data['rules'][0]['direction'] = $type;
        $data['rules'][0][$ips] = array(
            $ip
        );
        $data['rules'][0]['protocol'] = $protocol;
        $data['rules'][0]['port'] = $port;
        $data['apply_to'][0]['type'] = 'server';
        $data['apply_to'][0]['server']['id'] = $serverid;
        return $this->post('firewalls', $data);
    }

    public function codeToCountryName($code)
    {

        $countries = array(
            "BD" => "Bangladesh",
            "BE" => "Belgium",
            "BF" => "Burkina Faso",
            "BG" => "Bulgaria",
            "BA" => "Bosnia and Herzegovina",
            "BB" => "Barbados",
            "WF" => "Wallis and Futuna",
            "BL" => "Saint Barthelemy",
            "BM" => "Bermuda",
            "BN" => "Brunei",
            "BO" => "Bolivia",
            "BH" => "Bahrain",
            "BI" => "Burundi",
            "BJ" => "Benin",
            "BT" => "Bhutan",
            "JM" => "Jamaica",
            "BV" => "Bouvet Island",
            "BW" => "Botswana",
            "WS" => "Samoa",
            "BQ" => "Bonaire, Saint Eustatius and Saba ",
            "BR" => "Brazil",
            "BS" => "Bahamas",
            "JE" => "Jersey",
            "BY" => "Belarus",
            "BZ" => "Belize",
            "RU" => "Russia",
            "RW" => "Rwanda",
            "RS" => "Serbia",
            "TL" => "East Timor",
            "RE" => "Reunion",
            "TM" => "Turkmenistan",
            "TJ" => "Tajikistan",
            "RO" => "Romania",
            "TK" => "Tokelau",
            "GW" => "Guinea-Bissau",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GS" => "South Georgia and the South Sandwich Islands",
            "GR" => "Greece",
            "GQ" => "Equatorial Guinea",
            "GP" => "Guadeloupe",
            "JP" => "Japan",
            "GY" => "Guyana",
            "GG" => "Guernsey",
            "GF" => "French Guiana",
            "GE" => "Georgia",
            "GD" => "Grenada",
            "GB" => "United Kingdom",
            "GA" => "Gabon",
            "SV" => "El Salvador",
            "GN" => "Guinea",
            "GM" => "Gambia",
            "GL" => "Greenland",
            "GI" => "Gibraltar",
            "GH" => "Ghana",
            "OM" => "Oman",
            "TN" => "Tunisia",
            "JO" => "Jordan",
            "HR" => "Croatia",
            "HT" => "Haiti",
            "HU" => "Hungary",
            "HK" => "Hong Kong",
            "HN" => "Honduras",
            "HM" => "Heard Island and McDonald Islands",
            "VE" => "Venezuela",
            "PR" => "Puerto Rico",
            "PS" => "Palestinian Territory",
            "PW" => "Palau",
            "PT" => "Portugal",
            "SJ" => "Svalbard and Jan Mayen",
            "PY" => "Paraguay",
            "IQ" => "Iraq",
            "PA" => "Panama",
            "PF" => "French Polynesia",
            "PG" => "Papua New Guinea",
            "PE" => "Peru",
            "PK" => "Pakistan",
            "PH" => "Philippines",
            "PN" => "Pitcairn",
            "PL" => "Poland",
            "PM" => "Saint Pierre and Miquelon",
            "ZM" => "Zambia",
            "EH" => "Western Sahara",
            "EE" => "Estonia",
            "EG" => "Egypt",
            "ZA" => "South Africa",
            "EC" => "Ecuador",
            "IT" => "Italy",
            "VN" => "Vietnam",
            "SB" => "Solomon Islands",
            "ET" => "Ethiopia",
            "SO" => "Somalia",
            "ZW" => "Zimbabwe",
            "SA" => "Saudi Arabia",
            "ES" => "Spain",
            "ER" => "Eritrea",
            "ME" => "Montenegro",
            "MD" => "Moldova",
            "MG" => "Madagascar",
            "MF" => "Saint Martin",
            "MA" => "Morocco",
            "MC" => "Monaco",
            "UZ" => "Uzbekistan",
            "MM" => "Myanmar",
            "ML" => "Mali",
            "MO" => "Macao",
            "MN" => "Mongolia",
            "MH" => "Marshall Islands",
            "MK" => "Macedonia",
            "MU" => "Mauritius",
            "MT" => "Malta",
            "MW" => "Malawi",
            "MV" => "Maldives",
            "MQ" => "Martinique",
            "MP" => "Northern Mariana Islands",
            "MS" => "Montserrat",
            "MR" => "Mauritania",
            "IM" => "Isle of Man",
            "UG" => "Uganda",
            "TZ" => "Tanzania",
            "MY" => "Malaysia",
            "MX" => "Mexico",
            "IL" => "Israel",
            "FR" => "France",
            "IO" => "British Indian Ocean Territory",
            "SH" => "Saint Helena",
            "FI" => "Finland",
            "FJ" => "Fiji",
            "FK" => "Falkland Islands",
            "FM" => "Micronesia",
            "FO" => "Faroe Islands",
            "NI" => "Nicaragua",
            "NL" => "Netherlands",
            "NO" => "Norway",
            "NA" => "Namibia",
            "VU" => "Vanuatu",
            "NC" => "New Caledonia",
            "NE" => "Niger",
            "NF" => "Norfolk Island",
            "NG" => "Nigeria",
            "NZ" => "New Zealand",
            "NP" => "Nepal",
            "NR" => "Nauru",
            "NU" => "Niue",
            "CK" => "Cook Islands",
            "XK" => "Kosovo",
            "CI" => "Ivory Coast",
            "CH" => "Switzerland",
            "CO" => "Colombia",
            "CN" => "China",
            "CM" => "Cameroon",
            "CL" => "Chile",
            "CC" => "Cocos Islands",
            "CA" => "Canada",
            "CG" => "Republic of the Congo",
            "CF" => "Central African Republic",
            "CD" => "Democratic Republic of the Congo",
            "CZ" => "Czech Republic",
            "CY" => "Cyprus",
            "CX" => "Christmas Island",
            "CR" => "Costa Rica",
            "CW" => "Curacao",
            "CV" => "Cape Verde",
            "CU" => "Cuba",
            "SZ" => "Swaziland",
            "SY" => "Syria",
            "SX" => "Sint Maarten",
            "KG" => "Kyrgyzstan",
            "KE" => "Kenya",
            "SS" => "South Sudan",
            "SR" => "Suriname",
            "KI" => "Kiribati",
            "KH" => "Cambodia",
            "KN" => "Saint Kitts and Nevis",
            "KM" => "Comoros",
            "ST" => "Sao Tome and Principe",
            "SK" => "Slovakia",
            "KR" => "South Korea",
            "SI" => "Slovenia",
            "KP" => "North Korea",
            "KW" => "Kuwait",
            "SN" => "Senegal",
            "SM" => "San Marino",
            "SL" => "Sierra Leone",
            "SC" => "Seychelles",
            "KZ" => "Kazakhstan",
            "KY" => "Cayman Islands",
            "SG" => "Singapore",
            "SE" => "Sweden",
            "SD" => "Sudan",
            "DO" => "Dominican Republic",
            "DM" => "Dominica",
            "DJ" => "Djibouti",
            "DK" => "Denmark",
            "VG" => "British Virgin Islands",
            "DE" => "Germany",
            "YE" => "Yemen",
            "DZ" => "Algeria",
            "US" => "United States",
            "UY" => "Uruguay",
            "YT" => "Mayotte",
            "UM" => "United States Minor Outlying Islands",
            "LB" => "Lebanon",
            "LC" => "Saint Lucia",
            "LA" => "Laos",
            "TV" => "Tuvalu",
            "TW" => "Taiwan",
            "TT" => "Trinidad and Tobago",
            "TR" => "Turkey",
            "LK" => "Sri Lanka",
            "LI" => "Liechtenstein",
            "LV" => "Latvia",
            "TO" => "Tonga",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "LR" => "Liberia",
            "LS" => "Lesotho",
            "TH" => "Thailand",
            "TF" => "French Southern Territories",
            "TG" => "Togo",
            "TD" => "Chad",
            "TC" => "Turks and Caicos Islands",
            "LY" => "Libya",
            "VA" => "Vatican",
            "VC" => "Saint Vincent and the Grenadines",
            "AE" => "United Arab Emirates",
            "AD" => "Andorra",
            "AG" => "Antigua and Barbuda",
            "AF" => "Afghanistan",
            "AI" => "Anguilla",
            "VI" => "U.S. Virgin Islands",
            "IS" => "Iceland",
            "IR" => "Iran",
            "AM" => "Armenia",
            "AL" => "Albania",
            "AO" => "Angola",
            "AQ" => "Antarctica",
            "AS" => "American Samoa",
            "AR" => "Argentina",
            "AU" => "Australia",
            "AT" => "Austria",
            "AW" => "Aruba",
            "IN" => "India",
            "AX" => "Aland Islands",
            "AZ" => "Azerbaijan",
            "IE" => "Ireland",
            "ID" => "Indonesia",
            "UA" => "Ukraine",
            "QA" => "Qatar",
            "MZ" => "Mozambique"
        );

        return $countries[$code];
    }

    public function formatSizeBytestoTB($bytes)
    {
        return round($bytes / 1024 / 1024 / 1024 / 1024, 2);
    }

    public function formatSizeBytestoMB($bytes)
    {
        return round($bytes / 1024 / 1024, 2);
    }

    public function formatBytes($bytes)
    {
        $unit = ["B", "KB", "MB", "GB", "TB"];
        $exp = floor(log($bytes, 1024)) | 0;
        return round($bytes / (pow(1024, $exp))) . ' ' . $unit[$exp];
    }

    //2022.2 Netowkrs (Private)
    public function getallNetworks()
    {
        return $this->get('networks');
    }

    public function getNetworksByName($name)
    {
        return $this->get('networks?name=' . $name);
    }

    public function getNetwork($networkId)
    {
        return $this->get('networks/' . $networkId);
    }

    public function deleteNetwork($networkId)
    {
        return $this->delete('networks/' . $networkId);
    }

    public function getallNetworkActions($networkId)
    {
        return $this->get('networks/' . $networkId . '/actions');
    }

    public function getNetworkAction($networkId, $actionId)
    {
        return $this->get('networks/' . $networkId . '/actions/' . $actionId);
    }

    public function addNetworkRoute($networkId, $destination, $gateway)
    {
        $data['destination'] = $destination;
        $data['gateway'] = $gateway;
        return $this->post('networks/' . $networkId . '/actions/add_route', $data);
    }

    public function deleteNetworkRoute($networkId, $destination, $gateway)
    {
        $data['destination'] = $destination;
        $data['gateway'] = $gateway;
        return $this->post('networks/' . $networkId . '/actions/delete_route', $data);
    }

    public function addSubnet($networkId, $ip_range, $network_zone)
    {
        $data['ip_range'] = $ip_range;
        $data['network_zone'] = $network_zone;
        $data['type'] = 'cloud';
        return $this->post('networks/' . $networkId . '/actions/add_subnet', $data);
    }

    public function deleteSubnet($networkId, $ip_range)
    {
        $data['ip_range'] = $ip_range;
        return $this->post('networks/' . $networkId . '/actions/delete_subnet', $data);
    }

    public function changeIPRange($networkId, $ip_range)
    {
        $data['ip_range'] = $ip_range;
        return $this->post('networks/' . $networkId . '/actions/change_ip_range', $data);
    }

    public function changeNetworkProtection($networkId, $status)
    {
        $data['delete'] = $status;
        return $this->post('networks/' . $networkId . '/actions/change_protection', $data);
    }

    public function createNetwork($ip_range, $name)
    {
        $data['ip_range'] = $ip_range;
        $data['name'] = $name;
        return $this->post('networks', $data);
    }

    public function detachNetworkfromServer($networkId, $serverId)
    {
        $data['network'] = $networkId;
        return $this->post('servers/' . $serverId . '/actions/detach_from_network', $data);
    }

    public function attachNetworkfromServer($networkId, $serverId)
    {
        $data['network'] = $networkId;
        return $this->post('servers/' . $serverId . '/actions/attach_to_network', $data);
    }
}
