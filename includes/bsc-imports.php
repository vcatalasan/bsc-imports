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
	static $users = 'import_users';
	static $transactions = 'import_transactions';

	function __construct() {
        self::$settings['bsc_imports_page_link'] = 'tools.php?page=bsc_membership_import';
		// add admin menu
		add_action('admin_menu', array($this, 'bp_imports_menu'));
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

        // set defaults
		if ( ! get_option( 'email-template' ) ) {
			$email_template = 'Hello {USERNAME}

Username: {USERNAME}
Password: {PASSWORD}
Login url: {LOGIN_URL}

Thanks
{SITE_ADMIN}';
			update_option( 'email-template', $email_template );

		}

		$html_message = array();

		//Check whether the curent user have the access or not
		if ( ! current_user_can( 'manage_options' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

		// if the form is submitted
		switch ( $_POST['mode'] ) {
            case 'upload':
                $_FILES['users_csv_file']['name'] and $html_message[] = $this->csv2sql('users_csv_file', 'import_users') and update_option('users_uploaded', time());
                $_FILES['transactions_csv_file']['name'] and $html_message[] = $this->csv2sql('transactions_csv_file', 'import_transactions') and update_option('transactions_uploaded', time());
                break;

            case 'import':
                $html_message[] = $this->buddypress_import() and $html_message[] = $this->pmpro_import();
                break;

            case 'save-email-template':
                update_option( 'email-template', $_POST['email-template'] );
                break;

        }

		// Get the members import form
		$this->get_form( $html_message );
	}


	function get_form( $html_message ) {
		?>
		<div class="wrap">
			<?php foreach($html_message as $message) echo "<p>$message</p>"; ?>
			<div id="icon-users" class="icon32"><br /></div>
			<h2>Membership Import</h2>
            <p style="color: red">Please make sure to have back up your database before proceeding!</p>

            <h3><input type="checkbox" /> Step 1: Upload Users and Transactions History from CSV files</h3>

			<p>Please select the <strong>CSV</strong> files you want to upload below</p>
            <form action="<?php echo self::$settings['bsc_imports_page_link'] ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="upload" />
                <table>
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

            <h3><input type="checkbox" /> Step 2: Import Users and Transactions History into BuddyPress and PMPro</h3>

            <form action="<?php echo self::$settings['bsc_imports_page_link'] ?>" method="post" enctype="multipart/form-data">
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
			<form action="<?php echo self::$settings['bsc_imports_page_link'] ?>" method="post" enctype="multipart/form-data">
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
            $html_message = '<div class="updated">';
            $html_message .= 'Please upload only csv file!!';
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
                $f = substr(preg_replace('/[^0-9a-z]/', '_', $f), 0, 20);
                // derive type from field name
                $t = preg_match('/date/i', $f) ? 'TIMESTAMP' : 'VARCHAR(50)';
                $field_count++;
                $fields[] = "`$f` $t";
            }
        }
        //add extra fields for tracking
        $fields[] = "`upload_date` TIMESTAMP";
        $fields[] = "`import_date` TIMESTAMP";
        $sql = "CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $fields) . ');';
        //echo $sql . "\n";
        //$wpdb->query($sql);

        while (($data = fgetcsv($handle)) !== FALSE) {
            $values = array();
            for ($i = 0; $i < $field_count; $i++) {
                $v = $data[$i];
                preg_match('/TIMESTAMP/', $fields[$i]) and $v = date('Y-m-d H:i:s', strtotime($v));
                $values[] = '\'' . addslashes($v) . '\'';
            }
            //set upload date only
            $values[] = '\'' . addslashes(date('Y-m-d H:i:s')) . '\'';
            $sql = "INSERT into $table values(" . implode(', ', $values) . ');';
            //echo $sql . "\n";
            //$wpdb->query($sql);
        }
        fclose($handle);
        ini_set('auto_detect_line_endings', FALSE);

        $html_message = '<div class="updated">';
        $html_message .= "$filename has been uploaded to $table table";
        $html_message .= '</div>';
        return $html_message;
    }

    function buddypress_import()
    {return 'users imported to BuddyPress';
        global $wpdb;

        $error_message = $not_imported_usernames = array();

        $bp_status = is_plugin_active( 'buddypress/bp-loader.php' );
        if ( $bp_status ) {
            // Check whether the avatars directory present or not. If not then create.
            $bp_plugin_details = get_plugin_data( ABSPATH .'wp-content/plugins/buddypress/bp-loader.php' );
            $bp_plugin_version = $bp_plugin_details['Version'];

            if ( $bp_plugin_version < 1.8 ) {
                define( 'AVATARS', ABSPATH . 'assets/avatars' );
            } else {
                define( 'AVATARS', ABSPATH . 'wp-content/uploads/avatars' );
            }
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

        if ( $bp_status ) {
            //Get the BP extra fields id name name
            $bp_xprofile_fields = $bp_xprofile_fields_with_default_value = array();

            $bp_extra_fields = $wpdb->get_results( 'SELECT id, type, name FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields' );

            $bpxfwdv_sql = 'SELECT name
                                FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
                                WHERE type
                                    IN ("checkbox", "multiselectbox", "selectbox", "radio")
                                    AND parent_id=0';
            $bp_xprofile_fields_with_default_value = $wpdb->get_col( $bpxfwdv_sql );

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
                $bp_xprofile_fields[$value->id] = $value->name;
                $bp_fields_type[$value->id] = $value->type;
            }
        }
        $ext = strtolower( end( explode( '.', $_FILES['csv_file']['name'] ) ) );

        if ( 'csv' !== $ext  ) {
            $html_message = '<div class="updated">';
            $html_message .= 'Please upload only csv file!!';
            $html_message .= '</div>';
        } else {
            $avatar = isset( $_POST['avatar'] ) ? $_POST['avatar'] : false;

            // Check whether the admin wants to upload members avatar or not. If yes then
            // Check whether the avatars directory present or not. If not then create.
            if ( $avatar ) if ( ! file_exists( AVATARS ) ) mkdir( AVATARS, 0777 );

            ini_set( 'auto_detect_line_endings', TRUE );
            $handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
            $first = true;
            $not_imported = '';
            $flag = 0;
            $user_import = 0;
            $not_import_message = '';
            $total_rows = $new_user_imported = $old_user_updated = $user_not_imported = 0;
            while ( ( $row = fgetcsv( $handle, filesize( $_FILES['csv_file']['tmp_name'] ), ',' ) ) !== false ) {
                $row = array_map( 'trim', $row );
                // If we are on the first line, get the columns name in headers array
                if ( $first ) {
                    $headers = $row;
                    $first = false;
                    continue;
                }
                $total_rows++;

                // Separate user data from meta
                $userdata = $usermeta = $bpmeta = $bp_provided_fields = array();

                foreach ( $row as $ckey => $cvalue ) {
                    if ( empty( $cvalue ) ) continue;

                    $column_name = $headers[$ckey];

                    $cvalue = utf8_encode( $cvalue );

                    if ( strpos( $cvalue, '::' ) ) {
                        $cvalue = explode( '::', $cvalue );
                        $cvalue = array_filter( $cvalue, function( $item ) { return !empty( $item[0] ); } );
                    }

                    if ( in_array( $column_name, $wp_userdata_fields ) ) $userdata[$column_name] = $cvalue;
                    else if ( $bp_status && array_search( $column_name, $bp_xprofile_fields ) ) {
                        $bp_provided_fields[] = $column_name;
                        $bpmeta[array_search( $column_name, $bp_xprofile_fields )] = $cvalue;
                    }
                    else $usermeta[$column_name] = $cvalue;
                }
                if ( !isset( $_POST['update_user'] ) && $bp_status ) {
                    $bp_left_fields = array_diff( $bp_xprofile_fields_with_default_value, $bp_provided_fields );

                    if ( count( $bp_left_fields ) ) {
                        foreach ( $bp_left_fields as $bp_left_field ) {
                            $bpf_sql = 'SELECT id, type
                                            FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
                                            WHERE name="' . $bp_left_field . '"
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
                if ( empty( $userdata ) ) continue;

                // If creating a new user and no password was set, let auto-generate one!
                if ( empty( $userdata['user_pass'] ) )
                    $userdata['user_pass'] = wp_generate_password( 12, false );

                $userdata['user_login'] = strtolower( $userdata['user_login'] );

                if ( ( $userdata['user_login'] == '' ) && ( $userdata['user_email'] == '' ) ) {
                    $error_message .= '<br />user_login or/and user_email needed to import members for row ' . $total_rows;
                    $user_not_imported++;
                    continue;
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
                } else {
                    $user_id = wp_insert_user( $userdata );
                }

                // Is there an error?
                if ( is_wp_error( $user_id ) ) {
                    $flag = 1;
                    $user_not_imported++;
                    $not_imported_usernames  .= '<b>' . $userdata['user_login'] . '</b> ' . $user_id->errors['existing_user_login'][0] . '<br />';
                } else {
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

                    //User count
                    if ( array_key_exists( 'ID', $userdata ) ) {
                        $old_user_updated++;
                    } else {
                        $new_user_imported++;
                    }

                    $user_import = 1;

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

                if ( $user_import === 0 && $user_import === 0 )
                    $not_import_message = 'No users imported.<br />';
                $not_import_message .= $not_imported_usernames;

                if ( $flag === 1 && $user_import === 1 ) {
                    $not_import_message = 'Following user(s) are not imported as they are already registered in your website:<br />';
                    $not_import_message .= $not_imported_usernames;
                }
            }

            $html_message = '<div class="updated">';
            $html_message .= $not_import_message;
            $html_message .= '<p style="color: #ff0000;">' . $error_message . '</p>';
            $html_message .= '<p>Total users in CSV: ' . $total_rows . '</p>';
            $html_message .= '<p>Total new users imported: '. $new_user_imported . '</p>';
            $html_message .= '<p>Total old users updated: '. $old_user_updated . '</p>';
            $html_message .= '<p style="color: #ff0000;">Total users not imported: ' . $user_not_imported . '</p>';
            $html_message .= "</div>";
        }

    }

    function pmpro_import()
    {return 'transactions imported to PMPro';

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