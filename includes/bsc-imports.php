<?php
/**
 * Created by PhpStorm.
 * User: val
 * Date: 5/13/15
 * Time: 12:47 PM
 */

class BSC_Imports
{
    static $settings;

    static $users_table = 'import_users';
	static $transactions_table = 'import_transactions';

    static $upload_error = array(
        UPLOAD_ERR_INI_SIZE => 'The file %s exceeds the maximum file size allowed for uploads. Try splitting your file into smaller sizes ',
        UPLOAD_ERR_PARTIAL => 'The uploaded file %s was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded'
    );

    static $email_template = 'Hello {USERNAME}

Username: {USERNAME}
Password: {PASSWORD}
Login url: {LOGIN_URL}

Thanks
{SITE_ADMIN}';

    var $users_uploaded, $transactions_uploaded;
    var $total_users_uploaded, $total_transactions_uploaded;
    var $uploaded;

    var $status, $membership_levels;

	function __construct() {
        // initialize values
        self::$settings['bsc_imports_page_link'] = 'tools.php?page=bsc_membership_import';
        self::$upload_error[UPLOAD_ERR_INI_SIZE] .= sprintf(' <= %dM each.', ini_get('upload_max_filesize'));

        $this->total_users_uploaded = $this->get_total_users_uploaded();
        $this->total_transactions_uploaded = $this->get_total_transactions_uploaded();
        $this->membership_levels = pmpro_getAllLevels(false, true);

        // add admin menu
		add_action('admin_menu', array($this, 'bp_imports_menu'));
        add_filter('user_info_mapping', array($this, 'user_info_mapping'));
        add_filter('transaction_info_mapping', array($this, 'transaction_info_mapping'), 10, 2);
        add_filter('normalize_field_name', array($this, 'normalize_field_name'), 10, 2);

        // add ajax calls
        add_action('wp_ajax_import_users', array($this, 'import_users'));
        add_action('wp_ajax_import_transactions', array($this, 'import_transactions'));
	}

	function bp_imports_menu() {
		$bmi_page_hook = add_submenu_page( 'tools.php',
			'Membership Import',
			'Membership Import',
			'manage_options',
			'bsc_membership_import',
			array($this, 'membership_import_page')
		);

		add_action( 'admin_print_scripts-$bmi_page_hook', array($this, 'membership_import_css'));
	}

	function membership_import_css() {
		wp_enqueue_style( 'bp-imports-style-css', plugin_dir_url( __FILE__ ) . '/bsc-imports.css' );
	}

	// show import form
	function membership_import_page() {
		global $wpdb;

        // set initial values
		if ( ! get_option( 'email-template' ) ) {
			update_option( 'email-template', self::$email_template );
		}

        $this->users_uploaded = $this->transactions_uploaded = 0;

        $html_message = array();

		//Check whether the curent user have the access or not
		if ( ! current_user_can( 'manage_options' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

		// if the form is submitted
		switch ( $_POST['mode'] ) {
            case 'upload':
                $_FILES['users_csv_file']['name'] and $html_message['users_upload'] = $this->csv2sql('users_csv_file', self::$users_table) and $this->users_uploaded = $this->uploaded;
                $_FILES['transactions_csv_file']['name'] and $html_message['transactions_upload'] = $this->csv2sql('transactions_csv_file', self::$transactions_table) and $this->transactions_uploaded = $this->uploaded;
                break;

            case 'import':
                $html_message['start_import'] = $this->start_import();
                break;

            case 'save-email-template':
                update_option( 'email-template', $_POST['email-template'] );
                break;

        }

        $this->total_users_uploaded = $this->get_total_users_uploaded();
        $this->total_transactions_uploaded = $this->get_total_transactions_uploaded();

        // Get the members import form
		$this->get_form( $html_message );
	}


	function get_form(array $html_message) {
		?>
		<div class="wrap">
			<div id="icon-users" class="icon32"><br /></div>
			<h2>Membership Import</h2>
            <p style="color: red">Please make sure to have back up your database before proceeding!</p>

            <h3>Step 1: Upload Users and Transactions History from CSV files</h3>

            <?php
            if ($html_message['users_upload']) echo $html_message['users_upload'];
            if ($html_message['transactions_upload']) echo $html_message['transactions_upload'];
            ?>

			<p>Please select the <strong>CSV</strong> files you want to upload below</p>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="upload" />
                <table>
                    <tr>
                        <td>
                            <?php if ($this->users_uploaded) echo "$this->users_uploaded users were uploaded!"; ?>
                        </td>
                        <td>
                            <?php if ($this->transactions_uploaded) echo "$this->transactions_uploaded transactions were uploaded"; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="users-csv-file">Users</label>
                            <input type="file" id="users-csv-file" name="users_csv_file" />
                        </td>
                        <td>
                            <label for="transactions-csv-file">Transactions</label>
                            <input type="file" id="transactions-csv-file" name="transactions_csv_file" />
                        </td>
                    </tr>
                </table>
                <br />
                <input type="submit" value="Upload Files" />
            </form>

            <h3>Step 2: Import Users and Transactions History into BuddyPress and PMPro</h3>

            <?php if ($html_message['start_import']) echo $html_message['start_import']; ?>

            <form action="" method="post" enctype="multipart/form-data">
                <table>
                    <colgroup>
                        <col style="width:50%">
                        <col style="width:50%">
                    </colgroup>
                    <tr>
                        <td>
                            <?php if ($this->total_users_uploaded) echo "<span id='total-users-uploaded'>$this->total_users_uploaded</span> users"; ?>
                        </td>
                        <td>
                            <?php if ($this->total_transactions_uploaded) echo "<span id='total-transactions-uploaded'>$this->total_transactions_uploaded</span> transactions"; ?>
                        </td>
                    </tr>
                </table>
                <br />
				<input type="hidden" name="mode" value="import" />
				<input type="submit" value="Import Users and Transactions" />
                <br /><br />
				<table>
					<tr valign="top">
						<th scope="row">Notification: </th>
						<td>
							<label for="new_member_notification">
								<input id="new_member_notification" name="new_member_notification" type="checkbox" value="1" <?php checked('1', $_POST['new_member_notification']) ?> />
								Send username and password to new users.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Custom Notification: </th>
						<td>
							<label for="custom_notification">
								<input id="custom_notification" name="custom_notification" type="checkbox" value="1" <?php checked('1', $_POST['custom_notification']) ?> />
								Send custom notification message to users.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Upload Avatar: </th>
						<td>
							<label for="avatar">
								<input id="avatar" name="avatar" type="checkbox" value="1" <?php checked('1', $_POST['avatar']) ?> />
								Upload user avatar from CSV file. You have to provide full path of the image.
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Notice: </th>
						<td>The CSV file should be in the following format:</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							1: Fields name should be at the top line in CSV file separated by comma(,) and delimited by double quote(").<br />
							2: For multivalued field value should be separate by :: in csv file
						</td>
					</tr>
				</table>
			</form>
			<form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="save-email-template" />
                <table>
					<tr>
						<th scope="row">Email Template</th>
						<td>
							<!--<textarea class="email-template" name="email-template"><?php echo get_option( 'email-template' ); ?></textarea>-->
							<?php
							$args = array(
								'media_buttons' => false,
								'textarea_name ' => 'email-template1',
							);
							wp_editor( get_option( 'email-template' ), 'email-template', $args );
							?>
							<br />Do not change {USERNAME}, {PASSWORD},  {LOGIN_URL}, {SITE_ADMIN}
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<input type="submit" class="save-email-template" value="Save email template"/>
						</td>
					</tr>
				</table>
			</form>
		</div>
	<?php
	}

	function quick_links() {
		?>
		<div>
			<h3>Quick Links</h3>
			<p><a href="mailto:youngtec@youngtechleads.com" target="_blank">Mail Me</a></p>
			<p><a href="http://www.youngtechleads.com/buddypress-members-import" target="_blank">Plugin home page</a></p>
			<p><a href="http://www.youngtechleads.com/buddypress-members-import-support" target="_blank">Plugin support page</a></p>
			<p><a href="http://www.youngtechleads.com/buddypress-members-import-review" target="_blank">Plugin review page</a></p>
			<p><a href="http://www.youngtechleads.com/buddypress-members-import-faq" target="_blank">Plugin FAQ page</a></p>
		</div>
	<?php
	}

    function change_time_out() {
        /* max timeout to allow for mass user upload. */
        ini_set( 'max_execution_time', 0 );
        ini_set( 'memory_limit', '256M' );
    }

    function csv2sql($file, $table)
    {
        global $wpdb;

        $this->change_time_out();

        // get structure from csv and insert db
        ini_set('auto_detect_line_endings', TRUE);

        $filename = $_FILES[$file]['name'];
        $fileupload = $_FILES[$file]['tmp_name'];

        $ext = strtolower(end(explode( '.', $filename)));

        if ('csv' !== $ext) {
            $html_message = '<div class="error">';
            $html_message .= 'Please upload only csv file!!';
            $html_message .= '</div>';
            return $html_message;
        }

        $upload_error = $_FILES[$file]['error'];

        // check upload error
        if ($upload_error) {
            $html_message = '<div class="error">';
            $html_message .= self::$upload_error[$upload_error] ? sprintf(self::$upload_error[$upload_error], $filename) : "Unable to upload file $filename (ERROR=$upload_error)";
            $html_message .= '</div>';
            return $html_message;
        }

        $handle = fopen($fileupload, 'r');

        // first row, structure
        if (($data = fgetcsv($handle)) === FALSE) {
            $html_message = '<div class="updated">';
            $html_message .= "Cannot read from csv $filename";
            $html_message .= '</div>';
            return $html_message;
        }
        $fields = array();
        $field_count = 0;
        for ($i = 0; $i < count($data); $i++) {
            $f = strtolower(trim($data[$i]));
            if ($f) {
                // normalize the field name, strip to 20 chars if too long
                $f = apply_filters('normalize_field_name', $f, 20);
                // derive type from field name
                $t = preg_match('/date/i', $f) ? 'DATETIME DEFAULT NULL' : 'VARCHAR(50)';
                $field_count++;
                $fields[] = "`$f` $t";
            }
        }
        //add extra fields for tracking
        $fields[] = "`upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $fields[] = "`upload_id` BIGINT(20) NOT NULL AUTO_INCREMENT";
        $fields[] = "`import_status` ENUM('inserted','updated','error') DEFAULT NULL";
        $fields[] = "`wp_user_id` BIGINT(20) DEFAULT NULL";
        $sql = "CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $fields) . ', PRIMARY KEY(`upload_id`));';
        //echo $sql . "\n";
        $wpdb->query($sql);

        $this->uploaded = 0;

        while (($data = fgetcsv($handle)) !== FALSE) {
            $values = array();
            for ($i = 0; $i < $field_count; $i++) {
                $v = $data[$i];
                preg_match('/TIMESTAMP|DATETIME/', $fields[$i]) and $v = date('Y-m-d H:i:s', strtotime($v));
                $values[] = '\'' . addslashes($v) . '\'';
            }
            //set default value
            $values[] = '\'' . addslashes(date('Y-m-d H:i:s')) . '\''; // upload date
            $values[] = 'NULL'; // upload_id
            $values[] = 'NULL'; // import_status
            $values[] = 'NULL'; // wp_user_id
            $sql = "INSERT into $table values(" . implode(', ', $values) . ');';
            //echo $sql . "\n";
            $wpdb->query($sql) and $this->uploaded++;
        }
        fclose($handle);
        ini_set('auto_detect_line_endings', FALSE);

        $html_message = '<div class="updated">';
        $html_message .= "$filename has been uploaded to $table table";
        $html_message .= '</div>';
        return $html_message;
    }

    function normalize_field_name($f, $max_length = 0)
    {
        $normalize = function($f, $max_length) {
            $f = strtolower(trim($f));
            $f and $f = preg_replace('/[^0-9a-z]/', '_', $f);
            $f and $max_length and $f = substr($f, 0, $max_length);
            return $f;
        };

        if (is_array($f))
            foreach ($f as $i => $name) { $f[$i] = $normalize($name, $max_length); }
        else
            $f = $normalize($f, $max_length);
        return $f;
    }

    function start_import()
    {
        ob_start();
        ?>
        <div class="updated">
            <div style="display:inline-block">
                <p>Total new users imported: <span id="total-users-imported">0</span></p>
                <p>Total old users updated: <span id="total-users-updated">0</span></p>
                <p style="color: #ff0000;">Total users not imported: <span id="total-users-not-imported">0</span></p>
                <ul id="users-not-imported-list"></ul>
            </div>
            <div style="display:inline-block;margin-left:120px">
                <p>Total transactions imported: <span id="total-transactions-imported">0</span></p>
                <p style="color: #ff0000;">Total transactions not imported: <span id="total-transactions-not-imported">0</span></p>
                <ul id="transactions-not-imported-list"></ul>
            </div>
            <form id="import-users-form" action="" method="post">
                <input id="upload-id" type="hidden" name="upload_id" value="10" />
                <input id="import-users-button" type="submit" value="Start" />
            </form>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var started = false;
                var options = {
                    data: {action: 'import_users'},
                    url: '<?php echo admin_url( 'admin-ajax.php') ?>',
                    method: 'post',
                    success: function (responseText, statusText, xhr, $form) {
                        if (responseText) {
                            var total_users_uploaded = parseInt($('#total-users-uploaded').html());
                            var total_new_users_imported = parseInt($('#total-users-imported').html());
                            var total_old_users_updated = parseInt($('#total-users-updated').html());
                            var total_users_not_imported = parseInt($('#total-users-not-imported').html()) ;
                            var new_users_imported, old_users_updated, users_not_imported;
                            var response = jQuery.parseJSON(responseText);
                            var ok, eof, error;
                            console.log(response);

                            //get result
                            ok = parseInt(response['ok']) === 1 ? true : false;
                            eof = parseInt(response['eof']) === 1 ? true : false;
                            error = response['error'] !== undefined ? true : false;


                            new_users_imported = parseInt(response['new_users_imported']['count']);
                            old_users_updated = parseInt(response['old_users_updated']['count']);
                            users_not_imported = parseInt(response['users_not_imported']['count']);

                            total_users_uploaded -= (new_users_imported + old_users_updated + users_not_imported);

                            //update stat
                            $('#total-users-imported').html(total_new_users_imported + new_users_imported);
                            $('#total-users-updated').html(total_old_users_updated + old_users_updated);
                            $('#total-users-not-imported').html(total_users_not_imported + users_not_imported);
                            $('#total-users-uploaded').html(total_users_uploaded);

                            // list users not imported
                            for (var i = 0; i < users_not_imported; i++) {
                                $('#users-not-imported-list').append("<li>" + response['users_not_imported']['users'][i] + "</li>");
                            }

                            if (eof || error) {
                                ok = false;
                                $('#import-users-button').attr('value', eof ? 'All Done!' : response['error']);
                                return;
                            }

                            if (started && ok) {
                                $('#import-users-form').submit();
                            }
                        }
                    }
                };

                $('#import-users-button').click(function() {
                    switch ($(this).attr('value')) {
                        case 'Start':
                            started = true;
                            $(this).attr('value', 'Cancel');
                            return true;

                        case 'Cancel':
                            started = false;
                            $(this).attr('value', 'Start');
                            return false;

                        default:
                            started = false;
                            return true;
                    }
                });

                $('#import-users-form').submit(function() {
                    options['data']['XDEBUG_SESSION_START'] = 'PhpStorm';
                    if (started) {
                        //get import settings from forms
                        $('#new_member_notification').is(':checked') && (options['data']['new_member_notification'] = $('#new_member_notification').attr('value'));
                        $('#custom_notification').is(':checked') && (options['data']['custom_notification'] = $('#custom_notification').attr('value'));
                        $('#avatar').is(':checked') && (options['data']['avatar'] = $('#avatar').attr('value'));
                        options['data']['upload_id'] = $('#upload-id').attr('value');
                        $.ajax(options);
                        return false;
                    }
                    return true;
                });
            })
        </script>
        <?php
        return ob_get_clean();
    }

    function import_users()
    {
        global $wpdb;

        // set default values
        $this->status = array(
            'new_users_imported' => array('count' => 0, 'users' => array()),
            'old_users_updated' => array('count' => 0, 'users' => array()),
            'users_not_imported' => array('count' => 0, 'users' => array())
        );

        $upload_id = $_POST['upload_id'];

        $bp_status = is_plugin_active( 'buddypress/bp-loader.php' );
        if ($bp_status) {
            // Check whether the avatars directory present or not. If not then create.
            $bp_plugin_details = get_plugin_data( ABSPATH .'wp-content/plugins/buddypress/bp-loader.php' );
            $bp_plugin_version = $bp_plugin_details['Version'];

            if ( $bp_plugin_version < 1.8 ) {
                define( 'AVATARS', ABSPATH . 'assets/avatars' );
            } else {
                define( 'AVATARS', ABSPATH . 'wp-content/uploads/avatars' );
            }
        } else {
            $this->status['error'] = 'BuddyPress plugin is not installed or activated';
            $this->return_result($this->status);
        }

        // User data fields list used to differentiate with user meta
        $wp_userdata_fields = array(
            'user_login', 'user_pass',
            'user_email', 'user_url', 'user_nicename',
            'display_name', 'user_registered', 'first_name',
            'last_name', 'nickname', 'description',
            'rich_editing', 'comment_shortcuts', 'admin_color',
            'use_ssl', 'show_admin_bar_front', 'show_admin_bar_admin',
            'role'
        );

        //Get the BP extra fields id name name
        $bp_xprofile_fields = $bp_xprofile_fields_with_default_value = array();

        $bp_extra_fields = $wpdb->get_results( 'SELECT id, type, name FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields' );

        $bpxfwdv_sql = 'SELECT name
                            FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
                            WHERE type
                                IN ("checkbox", "multiselectbox", "selectbox", "radio")
                                AND parent_id=0';
        $bp_xprofile_fields_with_default_value = apply_filters('normalize_field_name', $wpdb->get_col( $bpxfwdv_sql ), 20);

        // Get xprofile field visibility
        $bp_fields_visibility = $wpdb->get_results( 'SELECT object_id, meta_value
                                                            FROM ' . $wpdb->base_prefix . 'bp_xprofile_meta
                                                            WHERE meta_key = "default_visibility"'
        );

        $xprofile_fields_visibility = array( 1 => 'public' );

        foreach ( $bp_fields_visibility as $bp_field_visibility ) {
            $xprofile_fields_visibility[$bp_field_visibility->object_id] = $bp_field_visibility->meta_value;
        }

        //Create an array of BP fields
        foreach ( $bp_extra_fields as $value ) {
            $bp_xprofile_fields[$value->id] = apply_filters('normalize_field_name', $value->name, 20);
            $bp_fields_type[$value->id] = $value->type;
        }

        $avatar = !empty( $_POST['avatar'] ) ? $_POST['avatar'] : false;

        // Check whether the admin wants to upload members avatar or not. If yes then
        // Check whether the avatars directory present or not. If not then create.
        if ( $avatar ) if ( ! file_exists( AVATARS ) ) mkdir( AVATARS, 0777 );

        $users = $this->get_uploaded_users_info($upload_id);
        foreach ($users as $row) {

            $user = apply_filters('user_info_mapping', $row);

            // Separate user data from meta
            $userdata = $usermeta = $bpmeta = $bp_provided_fields = array();

            foreach ( $user as $ckey => $cvalue ) {
                if ( empty( $cvalue ) ) continue;

                $column_name = $ckey;
                $bp_field_id = array_search( $column_name, $bp_xprofile_fields );

                $cvalue = utf8_encode( $cvalue );

                if ( strpos( $cvalue, '::' ) ) {
                    $cvalue = explode( '::', $cvalue );
                    $cvalue = array_filter( $cvalue, function( $item ) { return !empty( $item[0] ); } );
                }

                if ( in_array( $column_name, $wp_userdata_fields ) )
                    $userdata[$column_name] = $cvalue;
                else if ( $bp_status && $bp_field_id ) {
                    $bp_provided_fields[] = $column_name;
                    $bpmeta[$bp_field_id] = $cvalue;
                }
                else $usermeta[$column_name] = $cvalue;
            }

            // If no user data, comeout!
            if ( empty( $userdata ) ) {
                $this->status['users_not_imported']['count']++;
                $this->status['users_not_imported']['users'][] = "${row['upload_id']}: no user data";
                //Update user import table
                $user['import_status'] = 'error';
                $this->update_imported_user_info($user);
                continue;
            }

            $bp_left_fields = array_diff( $bp_xprofile_fields_with_default_value, $bp_provided_fields );

            if ( count( $bp_left_fields ) ) {
                foreach ( $bp_left_fields as $bp_left_field ) {
                    $bp_field_id = array_search( $bp_left_field, $bp_xprofile_fields );
                    $bpf_sql = 'SELECT id, type
                                    FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
                                    WHERE id=' . $bp_field_id . '
                                        AND parent_id=0';
                    $bp_fields = $wpdb->get_results( $bpf_sql );

                    $bpfo_sql = 'SELECT name
                                     FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
                                     WHERE parent_id=' . $bp_fields[0]->id . '
                                        AND is_default_option=1';
                    $bp_field_options = $wpdb->get_results( $bpfo_sql );
                    $field_options = array();

                    if ( $bp_fields[0]->type == 'selectbox' || $bp_fields[0]->type == 'radio' ) {
                        $bpmeta[$bp_fields[0]->id] = $bp_field_options[0]->name;
                    } else {
                        foreach ( $bp_field_options as $bp_field_option ) {
                            $field_options[] = $bp_field_option->name;
                        }
                        $bpmeta[$bp_fields[0]->id] = maybe_unserialize( $field_options );
                    }
                }
            }

            // If creating a new user and no password was set, let auto-generate one!
            if ( empty( $userdata['user_pass'] ) )
                $userdata['user_pass'] = wp_generate_password( 12, false );

            $userdata['user_login'] = strtolower( $userdata['user_login'] );

            if ( ( $userdata['user_login'] == '' ) && ( $userdata['user_email'] == '' ) ) {
                $this->status['users_not_imported']['count']++;
                $this->status['users_not_imported']['users'][] = "${row['upload_id']}: no user login/email";
                //Update user import table
                $user['import_status'] = 'error';
                $this->update_imported_user_info($user);
                continue;
            }
            else if ( $userdata['user_login'] == '' )
                $userdata['user_login'] = $userdata['user_email'];
            else if ( $userdata['user_email'] == '' )
                $userdata['user_email'] = $userdata['user_login'];

            //Check whether the user already exist or not
            $user_details = get_user_by( 'email', $userdata['user_email'] );
            empty($user_details) and $user_details = get_user_by( 'login', $userdata['user_login'] );

            //If user already exists then assign ID and update the account.
            if ($user_details) {
                $userdata['ID'] = $user_details->data->ID;
                unset( $userdata['user_pass'] );    //do not update password
                $user_id = wp_update_user( $userdata );
                $user_import = 2;
            } else {
                $user_id = wp_insert_user( $userdata );
                $user_import = 1;
            }

            // Is there an error?
            if ( is_wp_error( $user_id ) ) {
                $this->status['users_not_imported']['count']++;
                $this->status['users_not_imported']['users'][] = $userdata['user_login'] . ' ' . $user_id->errors['existing_user_login'][0];
                //Update user import table
                $user['import_status'] = 'error';
                $this->update_imported_user_info($user);
                continue;
            } else {
                $user['wp_user_id'] = $user_id;

                //Upload user avatar if permission granted.
                if ( $bp_status && $avatar ) {
                    $image_dir = AVATARS . '/'  . $user_id;
                    mkdir( $image_dir, 0777 );
                    $current_time = time();
                    $destination_bpfull = $image_dir . '/' . $current_time . '-bpfull.jpg';
                    $destination_bpthumb = $image_dir . '/' . $current_time . '-bpthumb.jpg';

                    if ( array_key_exists( 'avatar', $usermeta ) ) {
                        $usermeta['avatar'] = str_replace( ' ', '%20', $usermeta['avatar'] );
                        $bpfull = $bpthumb = wp_get_image_editor( $usermeta['avatar'] );

                        // Handle 404 avatar url
                        if ( !is_wp_error( $bpfull ) ) {
                            $bpfull->resize( 150, 150, true );
                            $bpfull->save( $destination_bpfull );
                            $bpthumb->resize( 50, 50, true );
                            $bpthumb->save( $destination_bpthumb );
                        }
                    }
                }

                // Insert xprofile field visibility state for user level.
                update_user_meta( $user_id, 'bp_xprofile_visibility_levels', $xprofile_fields_visibility );

                if ( isset( $bpmeta ) ) {
                    //Added an entry in user_meta table for current user meta key is last_activity
                    bp_update_user_last_activity( $user_id, date( 'Y-m-d H:i:s' ) );

                    foreach ( $bpmeta as $bpmetakeyid => $bpmetavalue ) {
                        xprofile_set_field_data( $bpmetakeyid, $user_id, $bpmetavalue );
                    }
                }

                // If no error, let's update the user meta too!
                if ( $usermeta ) {
                    if ( array_key_exists( 'member_group_ids', $usermeta ) ) {
                        $member_group_ids = $usermeta['member_group_ids'];
                        unset( $usermeta['member_group_ids'] );

                        if ( is_array( $member_group_ids ) ) {
                            //Attached members with BuddyPress groups
                            foreach ( $member_group_ids as $member_group_id ) {
                                groups_join_group( $member_group_id, $user_id );
                            }
                        } else {
                            groups_join_group( $member_group_ids, $user_id );
                        }
                    }

                    foreach ( $usermeta as $metakey => $metavalue ) {
                        $metavalue = maybe_unserialize( $metavalue );
                        update_user_meta( $user_id, $metakey, $metavalue );
                    }
                }

                if ( !empty( $_POST['new_member_notification'] ) ) {
                    if ( !empty( $_POST['custom_notification'] ) ) {
                        $this->send_notifiction_to_new_user( $user_id, $userdata['user_pass'] );
                    } else {
                        $this->wp_new_user_notification( $user_id, $userdata['user_pass'] );
                    }
                }
            }

            if ($user_import == 1) {
                $this->status['new_users_imported']['count']++;
                $this->status['new_users_imported']['users'][] = $userdata['user_login'];
                //Update user import table
                $user['import_status'] = 'inserted';
                $this->update_imported_user_info($user);
            } elseif ($user_import == 2) {
                $this->status['old_users_updated']['count']++;
                $this->status['old_users_updated']['users'][] = $userdata['user_login'];
                //Update user import table
                $user['import_status'] = 'updated';
                $this->update_imported_user_info($user);
            }
            $this->total_transactions_uploaded and $this->import_transactions($user_id);
        }

        // check for more records
        $upload_id > count($users) and $this->status['eof'] = 1;
        $this->status['ok'] = 1;
        $this->return_result($this->status);
    }

    function return_result($status)
    {
        echo json_encode($status);
        exit;
    }

    function import_transactions($user_id)
    {
        $user_details = get_user_by( 'id', $user_id );

        if (empty($user_details)) return;

        $transactions = $this->get_uploaded_user_transactions($user_details->user_login);
        foreach ($transactions as $row) {

            $transaction = apply_filters('transaction_info_mapping', $row, $user_id);

            $membership = $this->get_membership_info($transaction);

            //change membership level
            if (!empty($membership['membership_id'])) {
                pmpro_changeMembershipLevel($membership, $user_id);
            }

            //add order so integration with gateway works
            $order = $this->get_membership_order($transaction);
            $order->saveOrder();

            //update timestamp of order?
            if (!empty($transaction['timestamp'])) {
                $timestamp = $transaction['timestamp'];
                $order->updateTimeStamp(date("Y", $timestamp), date("m", $timestamp), date("d", $timestamp), date("H:i:s", $timestamp));
            }

            $transaction['wp_user_id'] = $user_id;
            $this->update_imported_transaction_info($transaction);
        }
    }

    function clean_field($field)
    {
        $field = preg_replace('/\[EMPTY]/i', '', $field);
        return trim($field);
    }

    function user_info_mapping(array $import)
    {
        $import = array_map(array($this, 'clean_field'), $import);
        $user_info = count($import) ? array_merge($import, array(
            //wp_user fields
            'user_login' => $import['username'],
            'user_pass' => $import[''],
            'user_nicename' => strtolower($import['first_name'] . '-' . $import['last_name']),
            'user_email' => $import['email'],
            'user_url' => $import[''],
            'user_registered' => $import[''],
            'user_activation_key' => $import[''],
            'user_status' => $import[''],
            'display_name' => ucwords($import['first_name'] . ' ' . $import['last_name']),
            'nickname' => ucwords($import['first_name'] . ' ' . $import['last_name']),
            'role' => 'subscriber'
        )) : array();
        return $user_info;
    }

    function transaction_info_mapping(array $import, $user_id)
    {
        $import = array_map(array($this, 'clean_field'), $import);
        $level = $this->get_membership_level($import['group']);
        //$expiration = empty($level['expiration_period']) ? null : date('Y-m-d', strtotime($import['transaction_date'] . " + ${$level['cycle_number']} ${$level['cycle_period']}"));
        $transaction_info = count($import) ? array_merge($import, array(
            'user_id' => $user_id,
            'membership_id' => $level['id'],
            'code_id' => $import[''],
            'initial_payment' => $import['amount'],
            'billing_amount' => $import['amount'],
            'cycle_number' => $level['cycle_number'],
            'cycle_period' => $level['cycle_period'],
            'billing_limit' => $level['billing_limit'],
            'trial_amount' => $import[''],
            'trial_limit' => $import[''],
            'status' => $import[''],
            'startdate' => $import['transaction_date'],
            'enddate' =>  $import['expiration_date'],
            'billing_street' => $import['address_1'],
            'billing_city' => $import['city'],
            'billing_state' => $import['state_province'],
            'billing_zip' => $import['zip'],
            'billing_country' => $import['country'],
            'billing_phone' => $import['phone'],
            'subtotal' => $import['amount'],
            'tax' => 0,
            'total' => $import['amount'],
            'payment_type' => $import['payment_type_name'],
            'cardtype' => $import[''],
            'gateway' => $this->get_payment_gateway($import['payment_type_name']),
            'payment_transaction_id' => $import['receipt_id'],
            'timestamp' => $import['submit_date']
        )) : array();
        //if (empty($expiration)) unset($transaction_info['enddate']);
        return $transaction_info;
    }

    function get_payment_gateway($payment_type)
    {
        $gateway = '';
        preg_match('/card/i', $payment_type) and $gateway = 'authorizenet';
        preg_match('/check|cash/i', $payment_type) and $gateway = 'check';
        return $gateway;
    }

    function get_membership_level($name)
    {
        $level = array();
        foreach ($this->membership_levels as $membership) {
            if (preg_match("/$name/i", $membership->name)) {
                $level = (array) $membership;
                break;
            }
        }
        return $level;
    }

    function get_membership_info(array $transaction)
    {
        $membership_info = array(
            'user_id' => $transaction['user_id'],
            'membership_id' => $transaction['membership_id'],
            'code_id' => $transaction['code_id'],
            'initial_payment' => $transaction['initial_payment'],
            'billing_amount' => $transaction['billing_amount'],
            'cycle_number' => $transaction['cycle_number'],
            'cycle_period' => $transaction['cycle_period'],
            'billing_limit' => $transaction['billing_limit'],
            'trial_amount' => $transaction['trial_amount'],
            'trial_limit' => $transaction['trial_limit'],
            'status' => $transaction['status'],
            'startdate' => $transaction['startdate'],
            'enddate' => $transaction['enddate']
        );
        return $membership_info;
    }

    function get_membership_order(array $transaction)
    {
        $order = new MemberOrder();
        $order->user_id = $transaction['user_id'];
        $order->membership_id = $transaction['membership_id'];
        $order->InitialPayment = $transaction['initial_payment'];
        $order->billing = new stdClass();
        $order->billing->street = $transaction['billing_street'];
        $order->billing->city = $transaction['billing_city'];
        $order->billing->state = $transaction['billing_state'];
        $order->billing->zip = $transaction['billing_zip'];
        $order->billing->country = $transaction['billing_country'];
        $order->billing->phone = $transaction['billing_phone'];
        $order->subtotal = $transaction['subtotal'];
        $order->tax = $transaction['tax'];
        $order->total = $transaction['total'];
        $order->payment_type = $transaction['payment_type'];
        $order->cardtype = $transaction['cardtype'];
        $order->gateway = $transaction['gateway'];
        $order->payment_transaction_id = $transaction['payment_transaction_id'];
        return $order;
    }

    function update_imported_user_info(array $user)
    {
        global $wpdb;
        $table_name = self::$users_table;
        $update = $wpdb->prepare("UPDATE $table_name SET import_status = '%s', wp_user_id = %d WHERE upload_id = %d",
            $user['import_status'], $user['wp_user_id'], $user['upload_id']);
        return $wpdb->query($update);
    }

    function update_imported_transaction_info(array $transaction)
    {
        global $wpdb;
        $table_name = self::$transactions_table;
        $update = $wpdb->prepare("UPDATE $table_name SET import_status = '%s', wp_user_id = %d WHERE upload_id = %d",
            $transaction['import_status'], $transaction['wp_user_id'], $transaction['upload_id']);
        return $wpdb->query($update);
    }

    function get_uploaded_users_info($rows)
    {
        global $wpdb;
        $table_name = self::$users_table;
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE import_status is NULL LIMIT %d", $rows);
        return $wpdb->get_results($query, ARRAY_A);
    }

    function get_uploaded_user_transactions($username)
    {
        global $wpdb;
        $table_name = self::$transactions_table;
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE username = '%s' ORDER BY transaction_date ASC", $username);
        return $wpdb->get_results($query, ARRAY_A);
    }

    function get_total_users_uploaded()
    {
        global $wpdb;
        $table_name = self::$users_table;
        $count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $count = $wpdb->get_var("SELECT count(*) FROM $table_name WHERE import_status is null");
        }
        return $count;
    }

    function get_total_transactions_uploaded($username = null)
    {
        global $wpdb;
        $table_name = self::$transactions_table;
        $count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $username and $username = " AND username = '$username'";
            $count = $wpdb->get_var("SELECT count(*) FROM $table_name WHERE import_status is null $username");
        }
        return $count;
    }

	function send_notifiction_to_new_user( $user_id, $user_pass ) {
		$this->wp_new_user_notification( $user_id, $user_pass, true );
	}

	function wp_new_user_notification( $user_id, $plaintext_pass = '' , $custom = false ) {
		global $custom_message;

		$user = new WP_User( $user_id );

		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );

		$message  = sprintf( __( 'New user registration on %s:' ), get_option( 'blogname' ) ) . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= sprintf( __( 'E-mail: %s' ), $user_email ) . "\r\n";

		if ( empty( $plaintext_pass ) )
			return;

		if ( $custom ) {
			$replace_terms = array( '{USERNAME}', '{PASSWORD}', '{SITE_ADMIN}', '{LOGIN_URL}' );
			$current_data = array( $user_login, $plaintext_pass, get_option( 'blogname' ), wp_login_url() );
			$custom_message = get_option( 'email-template' );
			$message  = str_replace( $replace_terms, $current_data, $custom_message ) . "\r\n\r\n";
		} else {
			$message  = __( 'Hi there,' ) . "\r\n\r\n";
			$message .= sprintf( __( "Welcome to %s! Here's how to log in:" ), get_option( 'blogname' ) ) . "\r\n\r\n";
			$message .= wp_login_url() . "\r\n";
			$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n";
			$message .= sprintf( __( 'Password: %s' ), $plaintext_pass ) . "\r\n\r\n";
			$message .= sprintf( __( 'If you have any problems, please contact me at %s.' ), get_option( 'admin_email' ) ) . "\r\n\r\n";
			$message .= __( 'Adios!' );
		}

		wp_mail(
			$user_email,
			sprintf( __( '[%s] Your login details' ), get_option( 'blogname' ) ),
			$message
		);
	}

}