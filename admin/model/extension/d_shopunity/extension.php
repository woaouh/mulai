<?php
/*
 *  location: admin/model
 */

class ModelExtensionDShopunityExtension extends Model {

    private $store_id = '';
    private $api = '';
    private $dir_root = '';

    public function __construct($registry){
        parent::__construct($registry);
        $this->api = new d_shopunity\API($registry);
        $this->store_id = $this->api->getStoreId();
        $this->dir_root = substr_replace(DIR_SYSTEM, '/', -8);
        $this->load->model('extension/d_shopunity/setting');
        $this->url_token = $this->model_extension_d_shopunity_setting->getUrlToken();
      
    }

    public function getExtensions($filter_data = array()){

        if(isset($filter_data['codename']) && is_array($filter_data['codename'])){
            $filter_data['codename'] = implode(',', $filter_data['codename']);
        }
        
        $json = $this->api->get('extensions', $filter_data);

        if(!$this->validate($json)){
            return false;
        }

        if($json){
            foreach($json as $key => $value){
                $json[$key] = $this->_extension($value);
            }  
        }
        return $json;  
    }

    public function getStoreExtensions($store_id = false){
        if(!$store_id){
            $store_id = $this->store_id;
        }
        $json = $this->api->get('stores/'.$store_id.'/extensions');

        if(!$this->validate($json)){
            return false;
        }
        
        if($json){
            foreach($json as $key => $value){
                $json[$key] = $this->_extension($value);
            }
        }
        
        return $json;
    }

    public function validate($json){
        if(!empty($json['errors'])){
            $this->session->data['error'] = $json['errors'][0]['message'];
            $this->load->model('extension/d_shopunity/account');
            $this->model_extension_d_shopunity_account->logout();
            return false;
        }
        return true;
    }

    public function getLocalExtensions($type = false){

        //Return mbooth files.
        $codenames = array();
        $this->load->model('extension/d_shopunity/mbooth');

        $installed_extensions = $this->model_extension_d_shopunity_mbooth->getExtensions();

        foreach($installed_extensions as $extension){
            if($type){
               if($type == $extension['type']){
                    $codenames[] = $extension['codename'];
               }
            }else{
                $codenames[] = $extension['codename'];
            } 
        }
        
        $filter_data = array(
            'codename' => implode(',', $codenames),
        );

        $extensions = $this->getExtensions($filter_data);
        if($extensions){
            foreach($extensions as $id => $extension){
                if($extension['store_extension']){
                    unset($extensions[$id]);
                }
            }
        }

        return $extensions;
    }

    public function getUnregisteredExtensions(){
        $codenames = array();
        $unregistered_extensions = array();
        $this->load->model('extension/d_shopunity/mbooth');

        $installed_extensions = $this->model_extension_d_shopunity_mbooth->getExtensions();
        foreach($installed_extensions as $extension){
            $codenames[] = $extension['codename'];
            $unregistered_extensions[$extension['codename']] = $extension;
        }

        
        $filter_data = array(
            'codename' => implode(',', $codenames)
        );

        $extensions = $this->getExtensions($filter_data);
        $result = array();
        
        if($extensions){
            foreach( $extensions as $extension ){
                unset($unregistered_extensions[$extension['codename']]);
            }

            
            foreach($unregistered_extensions as $extension ){
                $result[] = $this->_mbooth_extension($extension);
            }
        }
        return $result;
    }

    public function getTastableExtensions($tester_id){

        $json = $this->api->get('testers/'.$tester_id.'/extensions');

        if($json){
            foreach($json as $key => $value){
                $json[$key] = $this->_extension($value);
            }
        }

        return $json;

    }

    public function getLibraryExtensionsCount(){
        $this->load->model('extension/d_shopunity/mbooth');

        $installed_extensions = $this->model_extension_d_shopunity_mbooth->getExtensions();
        $count = 0;
        foreach($installed_extensions as $extension){
           if($extension['type'] == 'library'){
                $count++;
           }
        }
        return $count;
    }

    public function getExtension($extension_id){

        $json = $this->api->get('extensions/'.$extension_id);

        return $this->_extension($json);
    }

    public function getTestableExtension($tester_id, $extension_id, $extension_download_link_id){

        $data = array(
            'extension_download_link_id' => $extension_download_link_id
        );

        $json = $this->api->get('testers/'.$tester_id.'/extensions/'.$extension_id, $data);

        return $this->_extension($json);
    }
    

    public function purchaseExtension($extension_id, $extension_recurring_price_id){
        $data = array(
            'extension_id' => $extension_id,
            'extension_recurring_price_id' => $extension_recurring_price_id
        );
        $result = $this->api->post('stores/'.$this->store_id.'/extensions', $data);

        return $result;
    }

    public function suspendExtension($store_extension_id){
        $result = $this->api->delete('stores/'.$this->store_id.'/extensions/'.$store_extension_id);

        return $result;
    }

    public function approveExtension($tester_id, $extension_id, $data) {
        //not to copy
        // $data = array(
        //     'extension_download_link_id' => $data['extension_download_link_id'],
        //     'status' => $data['status'],
        //     'tester_comment' => $data['tester_comment'],
        // );
        $result = $this->api->post('testers/'. $tester_id. '/extensions/'. $extension_id .'/approve', $data);

        return $result;
    }

    public function getExtensionDownload($extension_id, $version = false){
        $data = array(
            'store_version' => VERSION,
            'store_id' => $this->store_id);

        if($version){
            $data['version'] = $version;
        }

        $result = $this->api->get('extensions/'.$extension_id.'/download', $data);
        return $result;
    }

    public function getExtensionDownloadByDownloadLinkId($extension_id, $extension_download_link_id){
        $data = array(
            'extension_download_link_id' => $extension_download_link_id,
            'store_version' => VERSION,
            'store_id' => $this->store_id);
        $result = $this->api->get('extensions/'.$extension_id.'/download', $data);
        return $result;
    }

    public function getExtensionDownloadByDownloadLinkIdForTesting($extension_id, $extension_download_link_id){
        $data = array(
            'extension_download_link_id' => $extension_download_link_id,
            'store_version' => VERSION,
            'store_id' => $this->store_id,
            'testing' => true);
        $result = $this->api->get('extensions/'.$extension_id.'/download', $data);
        return $result;
    }
    

    public function getExtensionDownloadByCodename($codename, $version = false){
        $data = array(
            'store_version' => VERSION,
            'store_id' => $this->store_id);

        if($version){
            $data['version'] = $version;
        }
        $result = $this->api->get('extensions/'.$codename.'/download', $data);
        return $result;
    }

    public function submitExtension($extension_id){

        $extension = $this->getExtension($extension_id);

        $this->load->model('extension/d_shopunity/mbooth');

        $data = $this->model_extension_d_shopunity_mbooth->getExtension($extension['codename']);

        $data['store_version'] = VERSION;

        $json = $this->api->post('extensions/'.$extension_id.'/submission', json_decode(json_encode($data), true));

        return $json;
    }


    public function isInstalled($codename){
        if(file_exists(DIR_SYSTEM . 'library/d_shopunity/extension/'.$codename.'.json')){
            return true;
        }
        //old format - depricated
        if(file_exists(DIR_SYSTEM . 'mbooth/extension/'.$codename.'.json')){
            return true;
        }
        return false;
    }

    public function isActivated($codename) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `code` = '" . $this->db->escape($codename) . "'");
        if(!empty($query->row)){
            return true;
        }
        return false;
    }

    public function _extension($data){

        $result = array();

        if(!empty($data)){
            $result = $data;
            $result['url'] = $this->url->link('extension/d_shopunity/extension/item', $this->url_token .'&extension_id='.$data['extension_id'],'SSL');
            if($data['prices']){
                $result['price'] = array();
                foreach( $data['prices'] as $price){
                    if($price['recurring_duration'] >= 365){
                        $result['price'] = $price;
                        break;
                    }
                }
            }

            $result['description'] = html_entity_decode($data['description']);
            $result['registered'] = true;
            $result['installed'] = $this->isInstalled($data['codename']);
            $result['admin'] = false;
            $result['activate'] = false;
            $result['deactivate'] = false;
            $result['update_available'] = false;
            $result['current_version'] = $result['version'];

            if($result['installed']){
                $mbooth = $this->model_extension_d_shopunity_mbooth->getExtension($data['codename']);

                try{
                    $semver = new Semver;

                    if(!empty($result['update_version']) && !empty($mbooth['version'])){
                        $result['update_available'] = $semver->gt($result['update_version'], $mbooth['version']);
                        $result['current_version'] = $mbooth['version'];
                        $result['version'] = $result['update_version'];
                    }
                  
                }catch(Exception $e){
                    //nothing;
                }

                if(isset($mbooth['index'])){
                    $result['admin'] =  $this->_ajax($this->url->link($mbooth['index'], $this->url_token , 'SSL'));
                }

                if(isset($mbooth['install'])){
                    if(isset($mbooth['install']['url'])){
                        $parts = explode('&', $mbooth['install']['url']);
                        $route = array_shift($parts);

                        $result['activate'] = str_replace('&amp;', '&', $this->url->link($route, implode('&', $parts).'&' . $this->url_token, 'SSL'));
                    }
                }

                if(isset($mbooth['uninstall'])){
                    if(isset($mbooth['uninstall']['url'])){
                        $parts = explode('&', $mbooth['uninstall']['url']);
                        $route = array_shift($parts);

                        $result['deactivate'] = str_replace('&amp;', '&', $this->url->link($route, implode('&', $parts).'&'. $this->url_token, 'SSL'));
                    }
                }
            }

            $result['purchase'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/purchase', $this->url_token . '&extension_id=' . $data['extension_id'] , 'SSL'));
            $result['popup_purchase'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/popup_purchase', $this->url_token . '&extension_id=' . $data['extension_id'] , 'SSL'));
            $result['install'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/install', $this->url_token . '&extension_id=' . $data['extension_id'] . ((isset($data['extension_download_link_id'])) ? '&extension_download_link_id=' . $data['extension_download_link_id'] : ''), 'SSL'));
            $result['popup'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/popup', $this->url_token . '&extension_id=' . $data['extension_id'] . ((isset($data['extension_download_link_id'])) ? '&extension_download_link_id=' . $data['extension_download_link_id'] : ''), 'SSL'));
            $result['test'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/test', $this->url_token . '&extension_id=' . $data['extension_id'] . ((isset($data['extension_download_link_id'])) ? '&extension_download_link_id=' . $data['extension_download_link_id'] : ''), 'SSL'));
            $result['update'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/install', $this->url_token  . '&extension_id=' . $data['extension_id']  . ((isset($data['extension_download_link_id'])) ? '&extension_download_link_id=' . $data['extension_download_link_id'] : ''), 'SSL'));
            $result['download'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/download', $this->url_token . '&codename='.$data['codename']. '&extension_id=' . $data['extension_id']  , 'SSL'));
            $result['uninstall'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/uninstall', $this->url_token . '&codename='.$data['codename']. '&extension_id=' . $data['extension_id'] , 'SSL'));
            $result['submit'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/submit', $this->url_token . '&extension_id='.$data['extension_id'] , 'SSL'));
            $result['json'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/json', $this->url_token . '&codename='.$data['codename']. '&extension_id=' . $data['extension_id'] . ((isset($data['extension_download_link_id'])) ? '&extension_download_link_id=' . $data['extension_download_link_id'] : ''), 'SSL'));
            $result['billing'] = $this->_ajax($this->url->link('extension/d_shopunity/order', $this->url_token . '&codename='.$result['codename'] , 'SSL'));
            $result['filemanager'] = $this->_ajax($this->url->link('extension/d_shopunity/filemanager', $this->url_token . '&codename='.$data['codename'] , 'SSL'));
            $result['developer_update'] = $this->_ajax($this->url->link('extension/d_shopunity/developer/update', $this->url_token . '&extension_id='.$data['extension_id'].'&developer_id='.$data['developer_id'] , 'SSL'));
         
            if(!empty($data['store_extension'])){
                $result['suspend'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/suspend', $this->url_token . '&store_extension_id='.$data['store_extension']['store_extension_id'] , 'SSL'));
            }else{
                $result['suspend'] = '';
            }
            if($result['testable']){
                $result['test'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/test', $this->url_token . '&extension_id=' . $data['extension_id'] . '&extension_download_link_id=' . $data['extension_download_link_id'] , 'SSL'));
                $result['approve'] = $this->_ajax($this->url->link('extension/d_shopunity/tester/approve', $this->url_token . '&extension_id=' . $data['extension_id'] . '&extension_download_link_id=' . $data['extension_download_link_id'] . '&status=1', 'SSL'));
                $result['disapprove'] = $this->_ajax($this->url->link('extension/d_shopunity/tester/approve', $this->url_token . '&extension_id=' . $data['extension_id'] . '&extension_download_link_id=' . $data['extension_download_link_id']. '&status=0', 'SSL'));
           
            }else{
                $result['test'] = '';
                $result['approve'] = '';
                $result['disapprove'] = '';
            }  
            if(!empty($data['mbooth']['tests'])){
                $result['unittestable'] = true;
                $result['popup_unittest'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/popup_unittest', $this->url_token . '&codename='.$data['codename'] , 'SSL'));
                $result['unittest'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/unittest', $this->url_token . '&codename='.$data['codename'] , 'SSL'));
            }else{
                $result['unittestable'] = false;
                $result['popup_unittest'] = '';
                $result['unittest'] = '';
            }
        }

        return $result;

    }

    private function _ajax($url){
        return html_entity_decode($url);
    }

    private function _mbooth_extension($data){

        $result = array();

        if(!empty($data)){
            $this->load->model('extension/d_shopunity/mbooth');
            $mbooth = $this->model_extension_d_shopunity_mbooth->getExtension($data['codename']);
            $this->load->model('tool/image');
            $image_thumb = (!empty($data['images']['thumb'])) ? $data['images']['thumb'] : 'https://shopunity.net/image/cache/no_image-640x400.jpg';
            $image_main = (!empty($data['images']['main'])) ? $data['images']['main'] : 'https://shopunity.net/image/cache/no_image-640x400.jpg';
            $result = $data;
            $result['name'] = trim($data['name']);
            $result['description_short'] = trim($data['description']);
            $result['url'] = '';
            $result['prices'] = '';
            $result['part_of_pack'] = '';
            $result['image'] = $image_main;
            $result['processed_images'] = array(
                0 => array(
                    'width' => 320,
                    'hight' => 200,
                    'url' => $image_thumb
                    ),
                1 => array(
                    'width' => 640,
                    'hight' => 400,
                    'url' => $image_thumb
                    )
                );
            $result['installed'] = true;
            $result['registered'] = false;
            $result['admin'] = false;
            $result['activate'] = false;
            $result['deactivate'] = false;
            if(isset($mbooth['index'])){
                $result['admin'] =  $this->_ajax($this->url->link($mbooth['index'], $this->url_token , 'SSL'));
            }
            if(isset($mbooth['install'])){
                if(isset($mbooth['install']['url'])){
                    $parts = explode('&', $mbooth['install']['url']);
                    $route = array_shift($parts);

                    $result['activate'] = str_replace('&amp;', '&', $this->url->link($route, implode('&', $parts).'&'. $this->url_token, 'SSL'));
                }
            }

            if(isset($mbooth['uninstall'])){
                if(isset($mbooth['uninstall']['url'])){
                    $parts = explode('&', $mbooth['uninstall']['url']);
                    $route = array_shift($parts);

                    $result['deactivate'] = str_replace('&amp;', '&', $this->url->link($route, implode('&', $parts).'&'. $this->url_token, 'SSL'));
                }
            }
            $result['store_extension'] = false;
            $result['tester_status_id'] = false;
            $result['tester_comment'] = false;
            $result['update_available'] = false;
            $result['current_version'] = $result['version'];

            $result['installable'] = true;
            $result['updatable'] = false;
            $result['downloadable'] = true;
            $result['purchasable'] = false;
            $result['suspendable'] = false;
            $result['submittable'] = false;
            $result['testable'] = false;
            $result['commercial'] = false;
            
            $result['purchase'] = '';
            $result['install'] = '';
            $result['update'] = '';
            $result['download'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/download', $this->url_token.'&codename='.$result['codename'] , 'SSL' ));
            $result['uninstall'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/uninstall', $this->url_token.'&codename='.$result['codename'] , 'SSL' ));
            $result['json'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/json', $this->url_token . '&codename='.$result['codename'] , 'SSL'));
            $result['billing'] = $this->_ajax($this->url->link('extension/d_shopunity/order', $this->url_token . '&codename='.$result['codename'] , 'SSL'));
            $result['filemanager'] = $this->_ajax($this->url->link('extension/d_shopunity/filemanager', $this->url_token . '&codename='.$data['codename'] , 'SSL'));
            $result['developer_update'] = '';
            $result['suspend'] = '';
            $result['test'] = '';
            $result['approve'] = '';
            $result['disapprove'] = '';
            if(!empty($result['mbooth']['tests'])){
                
                $result['unittestable'] = true;
                $result['popup_unittest'] = $this->_ajax($this->url->link('extension/d_shopunity/extension/popup_unittest', $this->url_token . '&codename='.$data['codename'] , 'SSL'));
            }else{
                $result['unittestable'] = false;
                $result['popup_unittest'] = '';
            }
        }

        return $result;
    }


}