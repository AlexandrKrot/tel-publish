<?php

class TelPublish
{
    private $token;
    private $chatId;
    private $rhash;
    private $urlApi = 'https://api.telegram.org/bot';
    
    
    static $init;
    
    function __construct(array $data)
    {
        if (!empty(self::$init)) return new WP_Error('duplicate_object', 'error');
        $this->token = $data['token'];
        $this->chatId = $data['chatId'];
        $this->rhash = $data['rhash'];
        
        
        add_action('edit_post', array($this, 'telPublishPost'));
        add_action('trash_post', array($this, 'telPublishPostdel'), 10, 2);
        add_action("admin_menu", array($this, "admin_add_menu"));
    }
    
    
    public static function init(array $data)
    {
        
        if (empty(self::$init)) self::$init = new self($data);
        return self::$init;
    }
    
    
    
    /**
    * telPublishPost actions save_post
    *   https://t.me/iv?url={}&rhash=ef700510df2706
    */
    
    public  function  telPublishPost($post_id)
    {
        
        if(get_post_status($post_id) != "publish") return false;    


        $url = get_permalink($post_id);
        $excerpt  = get_the_excerpt($post_id);
        $text = $excerpt;
        $text .= PHP_EOL;
        $link = 'https://t.me/iv?url=' . $url . '&rhash=' . $this->rhash;
        $text .= '<a href="' . $link . '">link</a>';
        
        $mess_id = get_post_meta($post_id, 'telpublishmessage', true);
        if ($mess_id) {
            $response =  $this->request('editMessageText', ['text' => $text, 'parse_mode' => 'HTML', 'message_id' => $mess_id]);
        } else {
            $response =  json_decode($this->request('sendMessage', ['text' => $text, 'parse_mode' => 'HTML']));
            if ($response->ok) {
                update_post_meta($post_id, 'telpublishmessage', $response->result->message_id);
            }
        }
    }
    /**
     * delete post in telegram
     */
    public function telPublishPostdel( $postid, $post){

        $mess_id = get_post_meta($postid, 'telpublishmessage', true);
        $this->request('deleteMessage', ['message_id' => $mess_id]);

    }
    
    /**
    * request 
    * @param $type - type sendMessage,forwardMessage,editMessageText
    * @param $content: array  
    */
    
    public function request($type, array $content)
    {
        $content['chat_id'] = $this->chatId;
        $url = $this->urlApi . $this->token . '/' . $type;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);       
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response === false) {
            $response = json_encode(['ok' => false, 'curl_error_code' => curl_errno($ch), 'curl_error' => curl_error($ch)]);
        }
        return $response;
    }

    /**
     * admin_add_menu      
     */

    public function admin_add_menu()
    {
        add_menu_page('TelPublish',  'Tel Publish', 'manage_options', 'telPublishSetting', array($this, 'telPublishSetting'));
        
    }
    
    public function telPublishSetting()
    {
        
        $path = plugin_dir_path(__FILE__) . '/plugin.php';
        $data = get_plugin_data($path);
        $message = (get_option('no_login_message')) ? get_option('no_login_message') : '';
        ?>
        <div class="wrap">
        <h2>PublishSetting</h2>
        
        <form method="post" action="options.php">
        <?php wp_nonce_field('update-options'); ?>
        
        <table class="form-table">
        
        <tr valign="top">
        <th scope="row">Token bot</th>
        <td><input type="text" name="tel_pub_token" value="<?php echo get_option('tel_pub_token'); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">chat Id</th>
        <td><input type="text" name="tel_pub_chat_id" value="<?php echo get_option('tel_pub_chat_id'); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Rhash.</th>
        <td><input type="text" name="tel_pub_rhash" value="<?php echo get_option('tel_pub_rhash'); ?>" /></td>
        </tr>
        
        </table>
        
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="tel_pub_token,tel_pub_chat_id,tel_pub_rhash" />
        
        <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>
        
        </form>
        </div>
        <?php
    }
}
