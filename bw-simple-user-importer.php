<?php

/**
 * Plugin Name: BW Simple User Importer
 * Description: Have you ever had a spreadsheet of emails and other user data that you wanted to import and turn into WordPress users? The BW Simple User Importer does this for you fast and easy!
 * Version: 1.0.7
 * Requires at least: 5.2
 * Requires PHP: 7.0
 * Author: Ben HartLenn
 * Author URI: https://bountifulweb.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bw_sui
 */

/**
 * Load plugin stylesheet
 */
add_action('admin_enqueue_scripts', 'bwsui_plugin_css');
function bwsui_plugin_css($hook)
{
	if ($hook == "users_page_simple-user-importer") {
		$plugin_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('bwsui-style', $plugin_url . 'css/bwsui-style.css');
	}
}

/**
 * Add menu item and admin page
 */
add_action("admin_menu", "bwsui_plugin_menu");
function bwsui_plugin_menu()
{
	add_submenu_page(
		"users.php",
		"BW Simple User Importer",
		"BW Simple User Importer",
		"manage_options",
		"simple-user-importer",
		"bwsui_plugin_page"
	);
}

/**
 * Callback function to display plugin dashboard page
 */
function bwsui_plugin_page()
{
?>
	<div id="bwsui-form-container">
		<h1>BW Simple User Importer</h1>

		<!-- Form -->
		<form style="text-align:center;" method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>
			<label>Select CSV file of user data to import... <input type="file" name="import_file"></label>
			<input type="submit" name="bwsui_import" value="Import Users">
		</form>
	</div>
<?php

	// Import CSV from form post request
	if (isset($_POST['bwsui_import'])) {

		// Get the File extension
		$extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);

		// If file extension is 'csv', and filename isn't empty
		if (!empty($_FILES['import_file']['name']) && $extension == 'csv') {

			// Open file in read mode
			$csvFile = fopen($_FILES['import_file']['tmp_name'], 'r');

			// initialize vars
			$bwsui_users = [];
			$rowNum = 0;

			// Read csv file and loop through each row
			while (($csvData = fgetcsv($csvFile)) !== FALSE) {

				// The below conditional should break the row loop to ignore blank lines, and go to the next line. Oddly though the php fgetcsv function doesn't seem to be recognizing blank lines and returning NULL for them as per manual https://www.php.net/manual/en/function.fgetcsv.php. Appears to be something to do with how different apps(excel vs numbers vs openoffice) translate line endings
				if (array(null) === $csvData) {
					continue;
				}

				// map row data to array and encode to utf8
				$csvRow = array_map("utf8_encode", $csvData);

				// store number of columns in row
				$colLen = count($csvRow);

				// If csv row is not empty, and it's not the first row, which should be set to being the header row...
				if (!empty($csvRow) && $rowNum >= 1) {

					// store username for each row 
					$ggcpui_username = trim($csvRow[3]); // column 3 is username

					// If username is not already in $bwsui_users array...
					if (!in_array($ggcpui_username, $bwsui_users)) {

						// ...store formatted user data in $bwsui_users array for wp_insert_user function to create a new user in database
						$bwsui_user_email = $ggcpui_username . "@wesgroup.ca"; // email will always be username@wesgroup.ca
						$bwsui_first_name = trim($csvRow[1]);
						$bwsui_last_name = trim($csvRow[0]);

						$bwsui_user = [
							'user_email' => $bwsui_user_email,
							'user_login' => $ggcpui_username,
							//'user_pass' => $password_string,
							'first_name' => $bwsui_first_name,
							'last_name' => $bwsui_last_name,
							'role' => 'subscriber',
						];

						// add user details to users array for displaying user information to admin screen after import
						$bwsui_users[$ggcpui_username] = [
							'user_email' => $bwsui_user_email,
							'first_name' => $bwsui_first_name,
							'last_name' => $bwsui_last_name,
						];

						
						// try to insert user into database
						$bwsui_user_id = wp_insert_user($bwsui_user);

						// If user was saved successfully...
						if (!is_wp_error($bwsui_user_id)) {

							// This line will send each new user an email about setting up a password for their new account
							//wp_new_user_notification( $bwsui_user_id, '', 'user' );

							// *** can do other things for user when it's created here ***

						} // end if user saving is not an error
						else {
							echo $bwsui_user_id->get_error_message();
						}
					} // end if user is not in $bwsui_users array		

				} // end if $csvRow is not empty

				// increment row number counter variable
				$rowNum++;
			} // end while loop that goes through the rows of csv file		

			// This code outputs a list of the users generated and their information
			echo "<h2>Number of imported users from CSV: " . count($bwsui_users) . "</h2>";

			foreach ($bwsui_users as $username => $user_data) {
				echo "<div>";
				echo "<h3>" . $username . "</h3>";
				echo "<p>Email: " . $user_data['user_email'] . "</p>";
				echo "<p>First Name: " . $user_data['first_name'] . "</p>";
				echo "<p>Last Name: " . $user_data['last_name'] . "</p>";
				echo "</div>";
			} // end foreach loop displaying users to admin screen after import

		} // end check if file submitted is a csv file

	} // end check if form submitted

} // end function bwsui_plugin_page

?>