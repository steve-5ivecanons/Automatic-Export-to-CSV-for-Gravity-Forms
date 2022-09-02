<?php


if ( class_exists( 'GFForms' ) ) {

    GFForms::include_addon_framework();

}


class GFAutomaticCSVAddOn extends GFAddOn {

    protected $_version = GF_AUTOMATIC_CSV_VERSION;
    protected $_min_gravityforms_version = '2.0';
    protected $_slug = 'automatic_csv_export_for_gravity_forms';
    protected $_path = 'gravityforms-automatic-csv-export/automatic_csv_export_for_gravity_forms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Automatic CSV Export for Gravity Forms Add-On';
    protected $_short_title = 'Automatic CSV Export';

    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFAutomaticCSVAddOn();
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();
        add_filter( 'gform_submit_button', array( $this, 'form_submit_button' ), 10, 2 );
    }

    public function init_admin() {
        parent::init_admin();
    }

    public function scripts() {
        $scripts = array(
            array(
                'handle'  => 'my_script_js',
                'src'     => $this->get_base_url() . '/js/my_script.js',
                'version' => $this->_version,
                'deps'    => array( 'jquery' ),
                'strings' => array(
                    'first'  => esc_html__( 'First Choice', 'csvexport' ),
                    'second' => esc_html__( 'Second Choice', 'csvexport' ),
                    'third'  => esc_html__( 'Third Choice', 'csvexport' )
                ),
                'enqueue' => array(
                    array(
                        'admin_page' => array( 'form_settings' ),
                        'tab'        => 'csvexport'
                    )
                )
            ),

        );

        return array_merge( parent::scripts(), $scripts );
    }

    public function styles() {
        $styles = array(
            array(
                'handle'  => 'my_styles_css',
                'src'     => $this->get_base_url() . '/css/my_styles.css',
                'version' => $this->_version,
                'enqueue' => array(
                    array( 'field_types' => array( 'poll' ) )
                )
            )
        );

        return array_merge( parent::styles(), $styles );
    }

    function form_submit_button( $button, $form ) {
        $settings = $this->get_form_settings( $form );
        if ( isset( $settings['enabled'] ) && true == $settings['enabled'] ) {
            $text   = $this->get_plugin_setting( 'mytextbox' );
            $button = "<div>{$text}</div>" . $button;
        }

        return $button;
    }


    // https://docs.gravityforms.com/gfaddon/
    public function form_settings_fields( $form ) {
        return array(
            array(
                'title'  => esc_html__( 'CSV Export Settings', 'csvexport' ),
                'fields' => array(
                    array(
                        'label'   => esc_html__( 'Enable Automatic export', 'csvexport' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_export',
                        'tooltip' => esc_html__( 'This will enable the automatic export of csv for this form.', 'csvexport' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Enabled', 'csvexport' ),
                                'name'  => 'enabled'
                            ),
                        )
                    ),
                    array(
                        'label'   => esc_html__( 'Search Criteria', 'csvexport' ),
                        'type'    => 'select',
                        'name'    => 'search_criteria',
                        'tooltip' => esc_html__( 'Choose the search criteria (yesterday, last seven days) for your export.', 'csvexport' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Previous Day', 'csvexport' ),
                                'value'  => 'previous_day'
                            ),
                            array(
                                'label' => esc_html__( 'Previous Week', 'csvexport' ),
                                'value'  => 'previous_week'
                            ),
                            array(
                                'label' => esc_html__( 'Previous Month', 'csvexport' ),
                                'value'  => 'previous_month'
                            ),
                            array(
                                'label' => esc_html__( 'Everything', 'csvexport' ),
                                'value'  => 'all'
                            ),
                        )
                    ),
                    array(
                        'label'   => esc_html__( 'Export frequency', 'csvexport' ),
                        'type'    => 'select',
                        'name'    => 'csv_export_frequency',
                        'tooltip' => esc_html__( 'This determines how frequently the export will be run and emailed to you.', 'csvexport' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Hourly', 'csvexport' ),
                                'value' => 'hourly'
                            ),
                            array(
                                'label' => esc_html__( 'Daily', 'csvexport' ),
                                'value' => 'daily'
                            ),
                            array(
                                'label' => esc_html__( 'Weekly', 'csvexport' ),
                                'value' => 'weekly'
                            ),
                            array(
                                'label' => esc_html__( 'Monthly', 'csvexport' ),
                                'value' => 'monthly'
                            )
                        )
                    ),
                    array(
                        'name'     => 'filter_by',
                        'label'    => esc_html__( 'Field To Filter By', 'csvexport' ),
                        'type'     => 'field_select',
                        'required' => false,
                        'tooltip'  => '<h6>' . esc_html__( 'Field To Filter By', 'csvexport' ) . '</h6>' . esc_html__( 'Select which Gravity Form field you want to filter export by.', 'csvexport' )
                    ),
                    array(
                        'label' => esc_html__( 'Filter Value', 'csvexport' ),
                        'type' => 'text',
                        'name' => 'filter_value',
                        'tooltip' => esc_html__( 'The value of the filter', 'csvexport' ),
                        'class' => 'medium'
                    ),                    
                    array(
                        'type'          => 'radio',
                        'name'          => 'format_export',
                        'label'         => esc_html__( 'Choose the export Format CSV/XLS', 'csvexport' ),
                        'default_value' => 'csv',
                        'horizontal'    => true,
                        'choices'       => array(
                            array(
                                'name'    => 'format_export',
                                'tooltip' => esc_html__( 'Sent the CSV Format', 'csvexport' ),
                                'label'   => esc_html__( 'Sent the CSV Format', 'csvexport' ),
                                'value'   => 'csv'
                            ),
                            array(
                                'name'    => 'format_export',
                                'tooltip' => esc_html__( 'Sent the XLS Format', 'csvexport' ),
                                'label'   => esc_html__( 'Sent the XLS Format', 'csvexport' ),
                                'value' => 'xls'
                            )
                        )
                    ),
                    array(
                        'label' => esc_html__( 'Email Subject', 'csvexport' ),
                        'type' => 'text',
                        'name' => 'email_subject',
                        'tooltip' => esc_html__( 'The e-mail will be sent with this subject', 'csvexport' ),
                        'class' => 'medium',
                        'placeholder' => 'Automatic Form Export'
                    ),
                    array(
                        'label' => esc_html__( 'Email Content', 'csvexport' ),
                        'type' => 'textarea',
                        'name' => 'email_content',
                        'tooltip' => esc_html__( 'The export will be sent with this content e-mail', 'csvexport' ),
                        'class' => 'medium',
                        'placeholder' => 'Export is attached to this message'
                    ),
                    array(
                        'label' => esc_html__( 'Email Address', 'csvexport' ),
                        'type' => 'text',
                        'name' => 'email_address',
                        'tooltip' => esc_html__( 'Comma seprated list of email address to send the export to', 'csvexport' ),
                        'class' => 'medium'
                    )
                ),
            ),
        );
    }
}
