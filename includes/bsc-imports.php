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

	function __construct() {
        // initialize values
        self::$settings['bsc_imports_page_link'] = 'tools.php?page=bsc_membership_import';
        self::$upload_error[UPLOAD_ERR_INI_SIZE] .= sprintf(' <= %dM each.', ini_get('upload_max_filesize'));

		// add admin menu
		add_action('admin_menu', array($this, 'bp_imports_menu'));
        add_filter('user_info_mapping', array($this, 'user_info_mapping'));
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
                            <?php if ($this->total_users_uploaded) echo "$this->total_users_uploaded users"; ?>
                        </td>
                        <td>
                            <?php if ($this->total_transactions_uploaded) echo "$this->total_transactions_uploaded transactions"; ?>
                        </td>
                    </tr>
                </table>
                <br />
				<input type="hidden" name="mode" value="import" />
				<input type="submit" value="Import Users and Transactions" />
                <br /><br />
				<table>
					<tr valign="top">
						<th scope="row">Update existing users: </th>
						<td>
							<label for="update_user">
								<input id="update_user" name="update_user" type="checkbox" value="1" />
								By checking this checkbox existing users data will be update.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Update existing users password: </th>
						<td>
							<label for="update_password">
								<input id="update_password" name="update_password" type="checkbox" value="1" />
								By checking this checkbox existing users passowrd will be update. Otherwise remain unchanged.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Notification: </th>
						<td>
							<label for="new_member_notification">
								<input id="new_member_notification" name="new_member_notification" type="checkbox" value="1" />
								Send username and password to new users.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Custom Notification: </th>
						<td>
							<label for="custom_notification">
								<input id="custom_notification" name="custom_notification" type="checkbox" value="1" />
								Send custom notification message to users.
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Upload Avatar: </th>
						<td>
							<label for="avatar">
								<input id="avatar" name="avatar" type="checkbox" value="1" />
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
        $fields[] = "`import_date` DATETIME DEFAULT NULL";
        $fields[] = "`upload_id` BIGINT(20) NOT NULL AUTO_INCREMENT";
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
            $values[] = 'NULL'; // import_date
            $values[] = 'NULL'; // upload_id
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
            <p>Total new users imported: <span id="total-users-imported">0</span></p>
            <p>Total old users updated: <span id="total-users-updated">0</span></p>
            <p style="color: #ff0000;">Total users not imported: <span id="total-users-not-imported">0</span></p>
            <form id="import-users-form" action="" method="post">
                <input id="upload-id" type="hidden" name="upload_id" value="1" />
                <input id="import-users-button" type="submit" value="Start" />
            </form>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var start = true;
                var options = {
                    data: {action: 'import_users'},
                    url: '<?php echo admin_url( 'admin-ajax.php') ?>',
                    method: 'post',
                    success: function (responseText, statusText, xhr, $form) {
                        if (responseText) {
                            var upload_id, status, message;
                            var total_users_imported = parseInt($('#total-users-imported').html());
                            var total_users_updated = parseInt($('#total-users-updated').html());
                            var total_users_not_imported = parseInt($('#total-users-not-imported').html()) ;
                            var response = jQuery.parseJSON(responseText);
                            console.log(response);

                            //get result
                            upload_id = parseInt(response['upload_id']);
                            status = parseInt(response['status']);
                            message = response['message'];

                            status === 0 && total_users_not_imported++;
                            status === 1 && total_users_imported++;
                            status === 2 && total_users_updated++;

                            //update stat
                            $('#upload-id').attr('value', upload_id);
                            $('#total-users-imported').html(total_users_imported);
                            $('#total-users-updated').html(total_users_updated);
                            $('#total-users-not-imported').html(total_users_not_imported);

                            if (start && upload_id > 0) {
                                $('#import-users-form').submit();
                            }
                            //response['tag'] && $(response['html']).insertBefore(response['tag']);
                        }
                    },
                    error: function(){
                        alert('error')
                    }

                };

                $('#import-users-button').click(function() {
                    if ($(this).attr('value') == 'Start') {
                        start = true;
                        $(this).attr('value', 'Cancel');
                        return true;
                    } else {
                        $(this).attr('value', 'Start');
                        start = false;
                        return false;
                    }
                });

                $('#import-users-form').submit(function() {
                    options['data']['upload_id'] = $('#upload-id').attr('value');
                    $.ajax(options);
                    return false;
                });
            })
        </script>
        <?php
        return ob_get_clean();
    }

    function import_users()
    {
        global $wpdb;

        $user_import = 0;

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
            $user_import = -1;
            $this->return_result(array('status' => $user_import, 'message' => 'BuddyPress plugin is not active!'));
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

        $avatar = isset( $_POST['avatar'] ) ? $_POST['avatar'] : false;

        // Check whether the admin wants to upload members avatar or not. If yes then
        // Check whether the avatars directory present or not. If not then create.
        if ( $avatar ) if ( ! file_exists( AVATARS ) ) mkdir( AVATARS, 0777 );

        $row = $this->get_uploaded_user_info($_POST['upload_id']);
        if (!empty($row)) {

            // Separate user data from meta
            $userdata = $usermeta = $bpmeta = $bp_provided_fields = array();

            foreach ( $row as $ckey => $cvalue ) {
                if ( empty( $cvalue ) ) continue;

                //$column_name = $headers[$ckey];
                $column_name = $ckey;
                $bp_field_id = array_search( $column_name, $bp_xprofile_fields );

                $cvalue = utf8_encode( $cvalue );

                if ( strpos( $cvalue, '::' ) ) {
                    $cvalue = explode( '::', $cvalue );
                    $cvalue = array_filter( $cvalue, function( $item ) { return !empty( $item[0] ); } );
                }

                if ( in_array( $column_name, $wp_userdata_fields ) ) $userdata[$column_name] = $cvalue;
                else if ( $bp_status && $bp_field_id ) {
                    $bp_provided_fields[] = $column_name;
                    $bpmeta[$bp_field_id] = $cvalue;
                }
                else $usermeta[$column_name] = $cvalue;
            }
            if ( !isset( $_POST['update_user'] ) && $bp_status ) {
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
            }
            // If no user data, comeout!
            if ( empty( $userdata ) ) {
                $user_import = -1;
                $this->return_result(array(
                    'status' => $user_import,
                    'message' => 'no user data'
                ));
            }

            // get upload id
            $upload_id = $_POST['upload_id'];
            $next_upload_id = $upload_id + 1;

            // If creating a new user and no password was set, let auto-generate one!
            if ( empty( $userdata['user_pass'] ) )
                $userdata['user_pass'] = wp_generate_password( 12, false );

            $userdata['user_login'] = strtolower( $userdata['user_login'] );

            if ( ( $userdata['user_login'] == '' ) && ( $userdata['user_email'] == '' ) ) {
                $this->return_result(array(
                    'status' => $user_import,
                    'message' => 'user_login or/and user_email needed to import member',
                    'upload_id' => $next_upload_id
                ));
            }
            else if ( $userdata['user_login'] == '' )
                $userdata['user_login'] = $userdata['user_email'];
            else if ( $userdata['user_email'] == '' )
                $userdata['user_email'] = $userdata['user_login'];

            if ( isset( $_POST['update_user'] ) ) {
                //Check whether the user already exist or not
                $user_details = get_user_by( 'email', $userdata['user_email'] );

                //If user already exists then assign ID and update the account.
                if ( $user_details ) {
                    $userdata['ID'] = $user_details->data->ID;

                    if ( isset( $_POST['update_password'] ) ) {
                        // $userdata['user_pass'] = wp_hash_password($userdata['user_pass']);
                    } else {
                        unset( $userdata['user_pass'] );
                    }
                }
                $user_id = wp_update_user( $userdata );
                $user_import = 2;
            } else {
                $user_id = wp_insert_user( $userdata );
                $user_import = 1;
            }

            // Is there an error?
            if ( is_wp_error( $user_id ) ) {
                $user_import = 0;
                $this->return_result(array(
                    'status' => $user_import,
                    'message' => $userdata['user_login'] . ' ' . $user_id->errors['existing_user_login'][0],
                    'upload_id' => $next_upload_id
                ));
            } else {
                //Update user import table
                $this->update_imported_user_info($row['upload_id']);

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

                if ( isset( $_POST['new_member_notification'] ) ) {
                    if ( isset( $_POST['custom_notification'] ) ) {
                        $this->send_notifiction_to_new_user( $user_id, $userdata['user_pass'] );
                    } else {
                        wp_new_user_notification( $user_id, $userdata['user_pass'] );
                    }
                }
            }
            $this->return_result(array(
                'status' => $user_import,
                'message' => 'user imported',
                'upload_id' => $next_upload_id
            ));
        }

        // check if there is any new uploaded user
        if ($this->get_total_users_uploaded())
            $next_upload_id = 0;
        else
            $next_upload_id = -1;
        $this->return_result(array(
            'status' => $user_import,
            'message' => 'no user data to import',
            'upload_id' => $next_upload_id
        ));
    }

    function return_result($message)
    {
        echo json_encode($message);
        exit;
    }

    function pmpro_import()
    {
        $html_message = '<div class="updated">';
        $html_message .= $not_import_message;
        $html_message .= '<p style="color: #ff0000;">' . $error_message . '</p>';
        $html_message .= '<p>Total new transactions imported: '. $new_transactions_imported . '</p>';
        $html_message .= '<p>Total old transactions updated: '. $old_transactions_updated . '</p>';
        $html_message .= '<p style="color: #ff0000;">Total transactions not imported: ' . $transactions_not_imported . '</p>';
        $html_message .= "</div>";
        return $html_message;
    }

    function user_info_mapping(array $import)
    {
        $user_info = count($import) ? array_merge(array(
            //wp_user fields
            'user_login' => $import['email'],
            'user_pass' => $import[''],
            'user_nicename' => $import[''],
            'user_email' => $import['email'],
            'user_url' => $import[''],
            'user_registered' => $import[''],
            'user_activation_key' => $import[''],
            'user_status' => $import[''],
            'display_name' => $import['']
        ), $import) : array();
        return $user_info;
    }

    function update_imported_user_info($upload_id)
    {
        global $wpdb;
        $table_name = self::$users_table;
        $current_date = date('Y-m-d H:i:s');
        $update= "UPDATE $table_name SET (import_date = '$current_date') WHERE upload_id = $upload_id";
        return $wpdb->query($update);
    }

    function get_uploaded_user_info($row)
    {
        global $wpdb;
        $table_name = self::$users_table;
        $query = "SELECT * FROM $table_name WHERE import_date is NULL";
        return apply_filters('user_info_mapping', $wpdb->get_row($query, ARRAY_A, $row));
    }

    function get_uploaded_user_transactions($email, $row_offset = 0)
    {
        global $wpdb;
        $table_name = self::$transactions_table;
        $query = "SELECT * FROM $table_name WHERE email = $email ORDER BY transaction_date ASC";
        return apply_filters('transaction_info_mapping', $wpdb->get_row($query, ARRAY_A, $row_offset));
    }

    function get_total_users_uploaded()
    {
        global $wpdb;
        $table_name = self::$users_table;
        $count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $count = $wpdb->get_var("SELECT count(*) FROM $table_name WHERE import_date is null");
        }
        return $count;
    }

    function get_total_transactions_uploaded($email = null)
    {
        global $wpdb;
        $table_name = self::$transactions_table;
        $count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $email and $mail = " AND email = $email";
            $count = $wpdb->get_var("SELECT count(*) FROM $table_name WHERE import_date is null $email");
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