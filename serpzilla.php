<?php
/*
Plugin Name: Serpzilla
Version: 0.30
Plugin URI:
Description: Meeting the challenge of boosting a web site high enough into the search engine rankings is a long and expensive process. But every site has its own resources inside, which will give an awesome effect instantly, to let its owner avoid any additional expenses. Actually, building inner links graph in a right way, better to say, correct inner linking, gives visible boosting in SERPs and traffic increase. However, the routine of inner link building is a time-consuming and complicated process. We propose a unique decision. Serpzilla allows you to combine accurate arrangement of links with the automatic placement of hundreds of links to thousands of pages. Work on the internal links on Your site is easy and convenient, as never before.

Please, notice that serpzilla plugin for Wordpress can be used only with your account in Serpzilla.com.

Author: Serpzilla
Author URI: http://www.serpzilla.com/
*/

/*
 Copyright 2011 Serpzilla (web : http://www.serpzilla.com/)
 */

/*
Copyright 2007-2011  Itex (web : http://itex.name/)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


class serpzilla {
    public  static $version = '0.30';
    public static $allOk = true;
    private static $error = '';
    public static $zilla;
    private static $links = array();
    private static $beforecontent = '';
    private static $aftercontent = '';
    private static $safeurl = '';
    private static $document_root = '';
    private static $debuglog = '';
    private static $memory_get_usage = 0; //start memory_get_usage
    private static $get_num_queries = 0; //start get_num_queries

    private static $_self;

    function plugins_loaded() {

        self::$document_root =
            ($_SERVER['DOCUMENT_ROOT'] != str_replace($_SERVER["SCRIPT_NAME"], '', $_SERVER["SCRIPT_FILENAME"]))
                ?
                (str_replace($_SERVER["SCRIPT_NAME"], '', $_SERVER["SCRIPT_FILENAME"]))
                :
                ($_SERVER['DOCUMENT_ROOT']);

        if (!get_option('serpzilla_install_date')) {
            update_option('serpzilla_install_date', time());
        }

        if (!function_exists(add_action)) {
            return 0;
        }

        add_action('parse_request', array(__CLASS__, 'serpzilla_init'));
        add_action('widgets_init', array(__CLASS__, 'serpzilla_widget_init'));
        add_action('admin_menu', array(__CLASS__, 'serpzilla_menu'));
        add_filter('plugin_row_meta', array(__CLASS__, 'serpzilla_plugin_row_meta'), 10, 2);

        add_action( 'admin_notices', array(__CLASS__, 'admin_notices'));
    }

    /**
     * Debug collector
     *
     */
    static function _debug($text = '') {
        self::$debuglog .= "\r\n" . $text . "\r\n";
    }

    static function dp($text) {
        if (get_option('serpzilla_global_debugenable')) {
            return '<span style="background-color: red; font-size: 20px; color: lightGreen;">' . $text . '</span>';
        }
        return '';
    }


    /**
     * plugin init static function
     *
     * @return  bool
     */
    static function serpzilla_init() {
        if (function_exists('memory_get_usage')) {
            self::$memory_get_usage = memory_get_usage();
        }
        if (function_exists('get_num_queries')) {
            self::$get_num_queries = get_num_queries();
        }

        self::_debug('REQUEST_URI = ' . $_SERVER['REQUEST_URI']);

        self::init_zilla();

        self::serpzilla_widget_init();

        add_filter('the_content', array(__CLASS__, 'serpzilla_replace'));
        add_filter('the_excerpt', array(__CLASS__, 'serpzilla_replace'));

        add_action('wp_footer', array(__CLASS__, 'serpzilla_footer'));


        if (function_exists('memory_get_usage')) {
            self::_debug("memory start/end/dif " . self::$memory_get_usage . '/' . memory_get_usage() . '/' . (memory_get_usage() - self::$memory_get_usage));
        }
        if (function_exists('get_num_queries')) {
            self::_debug("get_num_queries start/end/dif " . intval(self::$get_num_queries) . '/' . intval(get_num_queries()) . '/' . (intval(get_num_queries()) - intval(self::$get_num_queries)));
        }

        return 1;
    }

    /**
     * zilla init
     *
     * @return  bool
     */
    static function init_zilla() {

        self::_debug('_ZILLA_USER = ' . get_option('serpzilla_user'));

        self::$zilla = new Zilla_Client_Wordpress(array('zilla_user'=>get_option('serpzilla_user')));

        if (strlen(self::$zilla->_error)) {
            self::_debug('zilla error:' . var_export(self::$zilla->_error, true));
        }

        return 1;
    }

    static function admin_notices() {

        global $plugin_page;
        if ($plugin_page == basename(__FILE__)) {
            if (self::update_options()) {
                echo '<div id="message" class="updated"><p>' . __('Settings saved.', 'serpzilla') . '</p></div>';
            }
        }

        $error = '';

        if (self::validate_uid(get_option('serpzilla_user')) == false) {
            $error = __('You must <a href="%s">set valid serpzilla UID !!! </a>', 'serpzilla');
        } else {
            $have_places = false;
            foreach (self::places_for_show_links() as $var_name => $label) {
                if (get_option($var_name)) {
                    $have_places = true;
                    break;
                }
            }
            if (false == $have_places) {
                $error = __("Your links can't be displayed now. You must set the links quantity on the <a href=\"%s\">Serpzilla's settings page!!!</a>", 'serpzilla');
            }
        }

        if ($error) {
            echo "<div class='error'>" . sprintf($error, get_admin_url(null, 'options-general.php?page=serpzilla.php')) . "</div>";
        }
    }

    /** Output Functions  **/

    /**
     * Footer output
     *
     */
    static function serpzilla_footer() {
        $countfooter = get_option('serpzilla_links_footer');
        $countfooter = $countfooter == 'max' ? null : intval($countfooter);

        $footer = self::$zilla->return_links($countfooter);

        echo self::dp('start footer') . $footer . self::dp('end footer');

        if (get_option('serpzilla_global_debugenable')) {
            if ((intval(is_user_logged_in())) || intval(get_option('serpzilla_global_debugenable_forall'))) {
                self::$debuglog = str_ireplace('<!--', '<! --', self::$debuglog);
                self::$debuglog = str_ireplace('-->', '-- >', self::$debuglog);
                echo '<!--- SerpzillaDebugLogStart' . self::$debuglog . ' SerpzillaDebugLogEnd --->';
                echo '<!--- SerpzillaDebugErrorsStart' . self::$error . ' SerpzillaDebugErrorsEnd --->';
            }
        }
    }

    /**
     * Content links and before-after content links
     *
     * @param   string   $content   input text
     * @return  string    $content   outpu text
     */
    static function serpzilla_replace($content) {

        global $wp_current_filter;

        if (in_array('get_the_excerpt', $wp_current_filter)) {
            return $content; //что бы избежать вложенности
        }

        $before = $after = '';

        $count = get_option('serpzilla_links_beforecontent');
        $count = $count == 'max' ? null : intval($count);
        $before = self::$zilla->return_links($count);

        $count = get_option('serpzilla_links_aftercontent');
        $count = $count == 'max' ? null : intval($count);
        $after = self::$zilla->return_links($count);


        $content = self::dp('start beforecontent') . $before . self::dp('end beforecontent') . $content . self::dp('start aftercontent') . $after . self::dp('start aftercontent');
        self::_debug('links in content worked');

        return $content;
    }

    /**
     *
     *
     * @param   string   $domnod   $text
     * @return  string    $text
     */
    static function serpzilla_widget_init() {
       register_widget('Serpzilla_Widget');
    }


    /** Admin Functions  **/


    /**
     * Add admin menu to options
     *
     * @param   string   $domnod   $text
     * @return  string    $text
     */
    static function serpzilla_menu() {
        if (is_admin()) {
            add_options_page('Serpzilla', 'Serpzilla', 10, basename(__FILE__),
                             array(__CLASS__, 'serpzilla_admin'));
        }
    }

    /**
     *
     * Validate Serpzilla UID
     *
     * @static
     * @param string $uid
     * @return bool
     */

    static  function validate_uid($uid){
        return (strlen(trim($uid)) == 32);
    }

    /**
     *
     * @static
     * @return array()
     */
    static function places_for_show_links() {
        $places_for_show_links = array(
            'serpzilla_links_beforecontent' => __('Before content links', 'serpzilla'),
            'serpzilla_links_aftercontent' => __('After content links', 'serpzilla'),
            'serpzilla_links_footer' => __('Footer links', 'serpzilla'),

        );

        global $wp_registered_sidebars, $wp_registered_widgets;

        $serpzilla_widgets_keys = array();
        foreach ($wp_registered_widgets as $wkey => $widget) {
            if ($widget['callback'][0] instanceof Serpzilla_Widget) {
                $serpzilla_widgets_keys[] = $wkey;
            }
        }

        if (count($serpzilla_widgets_keys)) {

            $sidebars_widgets = wp_get_sidebars_widgets();

            $widget_key2sidebar_key = array();

            foreach ($sidebars_widgets as $sbname => $sbvalue) {
                if ('wp_inactive_widgets' == $sbname) {
                    continue;
                }
                foreach ($serpzilla_widgets_keys as $wkey) {
                    if (in_array($wp_registered_widgets[$wkey]['id'], $sbvalue)) {
                        $widget_key2sidebar_key[$wkey] = $sbname;
                        continue;
                    }
                }

            }
            if (count($widget_key2sidebar_key)) {
                foreach ($widget_key2sidebar_key as $wkey => $skey) {
                    $places_for_show_links['item_m_zilla_links_' . $wkey] = __('Widget at ', 'serpzilla') . $wp_registered_sidebars[$skey]['name'];
                }
            }
        }
        return $places_for_show_links;
    }


    static function update_options() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage options for this site.'));
        }

        $something_change = false;

        if (isset($_POST['submit'])) {
            $places_for_show_links = self::places_for_show_links();

            $something_change |= update_option('serpzilla_global_debugenable', isset($_POST['serpzilla_global_debugenable']));
            $something_change|= update_option('serpzilla_global_debugenable_forall', isset($_POST['serpzilla_global_debugenable_forall']));

            foreach ($places_for_show_links as $var_name => $label) {
                if (isset($_POST[$var_name])) {
                    $something_change|= update_option($var_name, $_POST[$var_name]);
                }
            }

            if (isset($_POST['serpzilla_user'])) {
                if (self::validate_uid($_POST['serpzilla_user'])) {
                    if (update_option('serpzilla_user', trim($_POST['serpzilla_user']))) {
                        $something_change = true;
                        delete_option('serpzilla_db_info');
                        delete_option('serpzilla_db_loadtime');
                    }
                }else{
                    update_option('serpzilla_user','');
                }
            }

        }
        return $something_change;
    }


    /**
     * Admin menu
     *
     */
    static function serpzilla_admin() {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage options for this site.'));
        }

        ?>
    <div class="wrap">

    <h2><img src="<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) ?>serp.png" style="vertical-align: bottom;" />
        <?php echo __('Serpzilla', 'serpzilla');?></h2>
        <form method="post">

            <?php
                        if (strlen(self::$error)) {
            echo '
                <div style="margin:10px auto; border:3px #f00 solid; background-color:#fdd; color:#000; padding:10px; text-align:center;">
                    ' . self::$error . '
                </div>';
        }
            ?>

            <?php
                $places_for_show_links = self::places_for_show_links();
            ?>

            <h3><?php echo __('Common settings', 'serpzilla'); ?></h3>
            <table class="form-table">

                <tr valign="top">
                    <th scope="row">
                        <label for=""><?php echo __('Your Serpzilla UID:', 'serpzilla');?></label>
                    </th>
                    <td>
                        <input type='text' size='50' name='serpzilla_user' id='serpzilla_user' 
                               value='<?php echo get_option('serpzilla_user');?>'/>
                        <span class="description"><?php printf(__("if you don`t know your UID take it <a href='%s'>here</a>"),'http://www.serpzilla.com/en/wm/sites/add/?from=wp-admin&v='.self::$version) ?></span>
                    </td>
                </tr>

                <?php
                    if(self::validate_uid(get_option('serpzilla_user'))){
                ?>
                    <tr>
                        <th scope="row">
                            <label for=""><?php echo __('Max links quantity:', 'serpzilla');?></label>
                        </th>
                        <td>
                        <?php
                            foreach ($places_for_show_links as $var_name => $label) {
                                self::_admin_select_max_links($var_name, get_option($var_name), $label);
                            }
                        ?>
                        </td>
                    </tr>

                    <tr valign="top">
                        <td colspan="2">
                            <?php
                            if (count($places_for_show_links)>3) {
                                printf( __("You can <a href='%s'>add more Serpzilla's widget</a> to another sidebars", 'serpzilla'),'widgets.php');
                            } else {
                                printf( __("You can <a href='%s'>add the Serpzilla's widget</a> to the one of your's sidebars for place links there", 'serpzilla'),'widgets.php');
                            }
                                ?>
                        </td>
                    </tr>
                <?php
                    }
                ?>

                </table>

            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
            </p>

        </form>
    </div>
            <?php
        return true;
    }


    /**
     * Admin menu input
     *
     */
    static function _admin_select_max_links($name, $value, $description = '', $style='') {
        $options = array(
                    '0' => __('Disabled', 'serpzilla'),
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    'max' => __('All remaining', 'serpzilla')
                );

        $style = $style == '' ? '' : "style=\"{$style}\"";

        echo '<select ' . $style . ' name="' . $name . '" id="' . $name . '">' . "\n";

        foreach ($options as $k => $v)
        {
            echo '<option value="' . $k . '"';
            if ($value == $k) {
                echo ' selected="selected"';
            }
            echo ">" . $v . "</option>\n";
        }
        echo "</select>\n";

        if($description!=''){
            echo '<label for="">' . $description . '.</label>';
        }

        echo "<br/>";
    }

    static function _activation() {
        global $wpdb;

        $db_version = '1.0';
        $table_name = $wpdb->prefix . "serpzilla";

        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        $wpdb->query("
            CREATE TABLE {$table_name} (
              uri varchar(255) NOT NULL,
              text text NOT NULL,
              PRIMARY KEY uri (uri)
            )"
        );


        $table_fields = $wpdb->get_results("DESCRIBE {$table_name};") ;

        $right_fields = serialize (array(
            0 => (object)array('Field' => 'uri', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => NULL, 'Extra' => ''),
            1 => (object)array('Field' => 'text', 'Type' => 'text', 'Null' => 'NO', 'Key' => '', 'Default' => NULL, 'Extra' => '')
        ));



        if(count($table_fields)==0){
            exit(__("Can't create table."));
        }elseif($right_fields!==serialize($table_fields)){
            exit(sprintf(__('Bad table created:<br> %s', 'serpzilla'),serialize($table_fields)));
        }

        add_option("serpzilla_db_version", $db_version);

        delete_option('serpzilla_db_info');
        delete_option('serpzilla_db_loadtime');
    }

    static function serpzilla_plugin_row_meta($links, $file) {
        if ($file == plugin_basename(__FILE__)) {
            $adminlink = get_bloginfo('url') . '/wp-admin/';
            $links[] = '<a href="' . $adminlink . 'options-general.php?page=serpzilla.php"> Settings</a>';
            $links[] = '<a target="_blank" href="http://help.serpzilla.com/en/faq">FAQs</a>';
            $links[] = '<a target="_blank" href="http://blog.serpzilla.com/">Blog</a>';
        }
        return $links;
    }
}


class Serpzilla_Widget extends WP_Widget {
        function Serpzilla_Widget() {
        parent::WP_Widget(false, $name = 'Serpzilla',array());
    }

    function form($instance) {
        $instance = wp_parse_args( (array) $instance, array( 'title' => __('Adv','serpzilla'), 'list_style' => 1) );
        $list_style = $instance['list_style'] ? 'checked="checked"' : '';
        ?>
         <p>
          <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
          <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $instance['title']; ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('max_links'); ?>">Max links quantity:</label>
            <?php
                echo serpzilla::_admin_select_max_links($this->get_field_name('max_links'), get_option('item_m_zilla_links_' . $this->id_base . '-' . $this->number), '', 'float:right');
            ?>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php echo $list_style; ?> id="<?php echo $this->get_field_id('list_style'); ?>" name="<?php echo $this->get_field_name('list_style'); ?>" /> <label for="<?php echo $this->get_field_id('list_style'); ?>"><?php _e('Show as list'); ?></label>
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {

        $new_instance = wp_parse_args((array)$new_instance, array('title' => __('Adv', 'serpzilla'), 'list_style' => '', 'max_links' => 0));

        $instance['title'] = $new_instance['title'];
        $instance['list_style'] = $new_instance['list_style'] ? 1 : 0;

        $max_links_was = get_option('item_m_zilla_links_' . $this->id_base . '-' . $this->number);
        if ($max_links_was != $new_instance['max_links']) {
            update_option('item_m_zilla_links_' . $this->id_base . '-' . $this->number, $new_instance['max_links']);
        }

        return $instance;
    }

    function widget($args, $instance) {
        extract($args, EXTR_SKIP);

        $countsidebar = get_option('item_m_zilla_links_' . $args['widget_id']);
        $countsidebar_int = $countsidebar == 'max' ? 999 : intval($countsidebar);

        if ($instance['list_style']) {
            $rowset = serpzilla::$zilla->return_links($countsidebar_int, false);
            if (count($rowset)) {
                echo $before_widget . $before_title . $instance['title'] . $after_title . '<ul>';

                foreach ($rowset as $row) {
                    echo '<li>' . $row . '</li>';
                }

                echo '</ul>' . $after_widget;
            }
        } else {
            echo $before_widget .
                 $before_title . $instance['title'] . $after_title .
                 '<div>' . serpzilla::$zilla->return_links($countsidebar_int) . '</div>'
                 . $after_widget;
        }
    }
}

require_once(WP_PLUGIN_DIR . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)) . 'zilla.php');
class Zilla_Client_Wordpress extends ZILLA_base{

    var $_user_agent = __CLASS__;

    function __construct($o){
        $this->_version=serpzilla::$version;
        return parent::__construct($o);
    }

    function _get_db_file(){
        return WP_PLUGIN_DIR . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)) . 'z.db';
    }

    function _read() {
        global $wpdb;

        $data = array(
            'info' => get_option('serpzilla_db_info'),
            'links' => array()
        );
        $table_name = $wpdb->prefix . "serpzilla";

        $links = $wpdb->get_var($wpdb->prepare("SELECT text FROM {$table_name} WHERE uri =%s ", $this->_request_uri));
        if (null != $links) {
            $data['links'][$this->_request_uri] = unserialize($links);
        }

        return $data;
    }

    function _write($data) {

        global $wpdb;
        $table_name = $wpdb->prefix . "serpzilla";

        $wpdb->query("DELETE FROM {$tabel_name}");

        if (count($data['links'])) {
            foreach ($data['links'] as $uri => $link_on_uri) {
                   $wpdb->insert($table_name, array('uri' => $uri, 'text' => serialize($link_on_uri)), array('%s', '%s'));
            }
        }

        update_option('serpzilla_db_info', $data['info']);
        $this->update_load_time(time());


        return true;
    }

    function update_load_time($time){
        update_option('serpzilla_db_loadtime', $time);
    }

    function get_load_time(){
        return get_option('serpzilla_db_loadtime');
    }

    function is_need_to_reload($data){
        return parent::is_need_to_reload($data);
    }
}

add_action('plugins_loaded', array('serpzilla', 'plugins_loaded'));
register_activation_hook(__FILE__, array('serpzilla', '_activation'));


?>