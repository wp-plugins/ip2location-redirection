<?php
/**
 * Plugin Name: IP2Location Redirection
 * Plugin URI: http://ip2location.com/tutorials/wordpress-ip2location-redirection
 * Description: Redirect visitors by their country.
 * Version: 1.1.2
 * Author: IP2Location
 * Author URI: http://www.ip2location.com
 */
defined( 'DS' ) or define( 'DS', DIRECTORY_SEPARATOR );
define( 'IP2LOCATION_REDIRECTION_ROOT', dirname( __FILE__ ) . DS );

class IP2LocationRedirection {
	function admin_options() {
		if ( is_admin() ) {
			// Include jQuery library.
			add_action( 'wp_enqueue_script', 'load_jquery' );

			// Find any .BIN files in current directory
			$files = scandir( IP2LOCATION_REDIRECTION_ROOT );

			foreach( $files as $file ){
				if ( strtoupper( substr( $file, -4 ) ) == '.BIN' ){
					update_option( 'ip2location_redirection_database', $file );
					break;
				}
			}

			$redirection_status = '';
			$mode_status = '';

			$enabled = ( isset( $_POST['countryCode'] ) && isset( $_POST['enableRedirection'] ) ) ? 1 : ( ( isset( $_POST['countryCode'] ) && !isset( $_POST['enableRedirection'] ) ) ? 0 : get_option( 'ip2location_redirection_enabled' ) );

			$lookup_mode = ( isset( $_POST['lookupMode'] ) ) ? $_POST['lookupMode'] : get_option( 'ip2location_redirection_lookup_mode' );
			$api_key = ( isset( $_POST['apiKey'] ) ) ? $_POST['apiKey'] : get_option( 'ip2location_redirection_api_key' );

			if ( isset( $_POST['countryCode'] ) && is_array( $_POST['countryCode'] ) ) {
				$index = 0;
				$rules = array();

				foreach( $_POST['countryCode'] as $country_code ) {
					if ( strlen( $country_code ) != 2 || empty($_POST['from'][$index]) || ($_POST['to'][$index] == 'url' && empty( $_POST['url'][$index] )) ) {
						continue;
					}

					if ( $_POST['from'][$index] == $_POST['to'][$index] ) {
						continue;
					}

					$duplicated = false;
					foreach( $rules as $rule ) {
						if ( $rule[0] == $country_code && $rule[1] == $_POST['from'][$index] ) {
							$duplicated = true;
							break;
						}
					}

					if ( $duplicated ) {
						continue;
					}

					if ( $_POST['to'][$index] != 'url' ) {
						$_POST['url'][$index] = '';
					}

					if ( $_POST['to'][$index] == 'url' && !filter_var( $_POST['url'][$index], FILTER_VALIDATE_URL ) ) {
						$redirection_status .= '
						<div id="message" class="error">
							<p><strong>' . $_POST['url'][$index] . '</strong> is not a valid URL, it has been excluded from saving.</p>
						</div>';

						continue;
					}

					$rules[] = array($country_code, $_POST['from'][$index], $_POST['to'][$index], $_POST['url'][$index], $_POST['statusCode'][$index]);

					$index++;
				}

				update_option( 'ip2location_redirection_enabled', $enabled );
				update_option( 'ip2location_redirection_rules', json_encode($rules) );

				$redirection_status .= '
				<div id="message" class="updated">
					<p>Changes saved.</p>
				</div>';
			}

			if( isset( $_POST['lookupMode'] ) ) {
				update_option( 'ip2location_redirection_lookup_mode', $lookup_mode );
				update_option( 'ip2location_redirection_api_key', $api_key );

				$mode_status .= '
				<div id="message" class="updated">
					<p>Changes saved.</p>
				</div>';
			}

			$pages = get_pages( array( 'post_status' => 'publish' ) );
			$posts = get_posts( array( 'post_status' => 'publish' ) );

			$from = array(
				array('', ''),
				array('any', 'Any Page'),
			);

			$to = array();
			$to[] = array( '', '' );
			$to[] = array( 'url', 'URL' );

			foreach( $pages as $page ) {
				$from[] = array( 'page-' . $page->ID, 'Page/' . ( ( $page->post_title ) ? $page->post_title : '(No Title)' ) );
				$to[] = array( 'page-' . $page->ID, 'Page/' . ( ( $page->post_title ) ? $page->post_title : '(No Title)' ) );
			}

			foreach($posts as $post){
				$from[] = array('post-' . $post->ID, 'Post/' . (($post->post_title) ? $post->post_title : '(No Title)'));
				$to[] = array('post-' . $post->ID, 'Post/' . (($post->post_title) ? $post->post_title : '(No Title)'));
			}

			$rows = '[["","","","",301]]';

			if( !is_null( $data = json_decode( get_option( 'ip2location_redirection_rules' ) ) ) ) {
				$rows = '[';
				foreach( $data as $values ) {
					$rows .= '["' . $values[0] . '","' . $values[1] . '","' . $values[2] . '","' . $values[3] . '",' . $values[4] . '],';
				}
				rtrim($rows, ',');
				$rows .= ']';

				if( empty( $data ) ) {
					$rows = '[["","","","",301]]';
				}
			}

			echo '
			<script>
				(function( $ ) {
					$(function(){
						var rows = ' . $rows . ';

						$.each(rows, function(index, row){
							addRow(row[0], row[1], row[2], row[3], row[4]);
						});

						$("#remove-all").on("click", function(){
							$("#redirection").html("");
							addRow("", "", "", "", 301);
						});

						$("#download").on("click", function(e){
							e.preventDefault();

							if ($("#productCode").val() == "" || $("#username").val() == "" || $("#password").val() == ""){
								return;
							}

							$("#download").attr("disabled", "disabled");
							$("#download-status").html(\'<div style="padding:10px; border:1px solid #ccc; background-color:#ffa;">Downloading \' + $("#productCode").val() + \' BIN database in progress... Please wait...</div>\');

							$.post(ajaxurl, { action: "update_ip2location_redirection_database", productCode: $("#productCode").val(), username: $("#username").val(), password: $("#password").val() }, function(response) {
								if(response == "SUCCESS") {
									alert("Downloading completed.");

									$("#download-status").html(\'<div id="message" class="updated"><p>Successfully downloaded the \' + $("#productCode").val() + \' BIN database. Please refresh information by <a href="javascript:;" id="reload">reloading</a> the page.</p></div>\');

									$("#reload").on("click", function(){
										window.location = window.location.href.split("#")[0];
									});
								}
								else {
									alert("Downloading failed.");

									$("#download-status").html(\'<div id="message" class="error"><p><strong>ERROR</strong>: Failed to download \' + $("#productCode").val() + \' BIN database. Please make sure you correctly enter the product code and login crendential. Please also take note to download the BIN product code only.</p></div>\');
								}
							}).always(function() {
								$("#productCode").val("DB1LITEBIN");
								$("#username").val("");
								$("#password").val("");
								$("#download").removeAttr("disabled");
							});
						});

						$("#form-redirection").on("submit", function(e){
							$(\'select[name="from[]"]\').each(function(){
								if($(this).val() == ""){
									alert("Please select Post/Page for redirection.");

									e.preventDefault();
								}
							});
						});

						$("#use-bin").on("click", function(){
							$("#bin-mode").show();
							$("#ws-mode").hide();

							$("html, body").animate({
								scrollTop: $("#use-bin").offset().top - 50
							}, 100);
						});

						$("#use-ws").on("click", function(){
							$("#bin-mode").hide();
							$("#ws-mode").show();

							$("html, body").animate({
								scrollTop: $("#use-ws").offset().top - 50
							}, 100);
						});

						$("#' . ( ( $lookup_mode == 'bin' ) ? 'bin-mode' : 'ws-mode' ) . '").show();
					});

					function addRow(countryCode, from, to, url, statusCode){
						var countries = {"":"","AF":"Afghanistan","AL":"Albania","DZ":"Algeria","AS":"American Samoa","AD":"Andorra","AO":"Angola","AI":"Anguilla","AQ":"Antarctica","AG":"Antigua And Barbuda","AR":"Argentina","AM":"Armenia","AW":"Aruba","AU":"Australia","AT":"Austria","AZ":"Azerbaijan","BS":"Bahamas","BH":"Bahrain","BD":"Bangladesh","BB":"Barbados","BY":"Belarus","BE":"Belgium","BZ":"Belize","BJ":"Benin","BM":"Bermuda","BT":"Bhutan","BO":"Bolivia, Plurinational State Of","BQ":"Bonaire, Sint Eustatius And Saba","BA":"Bosnia And Herzegovina","BW":"Botswana","BV":"Bouvet Island","BR":"Brazil","IO":"British Indian Ocean Territory","BN":"Brunei Darussalam","BG":"Bulgaria","BF":"Burkina Faso","BI":"Burundi","KH":"Cambodia","CM":"Cameroon","CA":"Canada","CV":"Cape Verde","KY":"Cayman Islands","CF":"Central African Republic","TD":"Chad","CL":"Chile","CN":"China","CX":"Christmas Island","CC":"Cocos (keeling) Islands","CO":"Colombia","KM":"Comoros","CG":"Congo","CD":"Congo, The Democratic Republic Of The","CK":"Cook Islands","CR":"Costa Rica","HR":"Croatia","CU":"Cuba","CW":"Cura\u00c7ao","CY":"Cyprus","CZ":"Czech Republic","CI":"C\u00d4te D\'Ivoire","DK":"Denmark","DJ":"Djibouti","DM":"Dominica","DO":"Dominican Republic","EC":"Ecuador","EG":"Egypt","SV":"El Salvador","GQ":"Equatorial Guinea","ER":"Eritrea","EE":"Estonia","ET":"Ethiopia","FK":"Falkland Islands (malvinas)","FO":"Faroe Islands","FJ":"Fiji","FI":"Finland","FR":"France","GF":"French Guiana","PF":"French Polynesia","TF":"French Southern Territories","GA":"Gabon","GM":"Gambia","GE":"Georgia","DE":"Germany","GH":"Ghana","GI":"Gibraltar","GR":"Greece","GL":"Greenland","GD":"Grenada","GP":"Guadeloupe","GU":"Guam","GT":"Guatemala","GG":"Guernsey","GN":"Guinea","GW":"Guinea-bissau","GY":"Guyana","HT":"Haiti","HM":"Heard Island And Mcdonald Islands","VA":"Holy See (vatican City State)","HN":"Honduras","HK":"Hong Kong","HU":"Hungary","IS":"Iceland","IN":"India","ID":"Indonesia","IR":"Iran, Islamic Republic Of","IQ":"Iraq","IE":"Ireland","IM":"Isle Of Man","IL":"Israel","IT":"Italy","JM":"Jamaica","JP":"Japan","JE":"Jersey","JO":"Jordan","KZ":"Kazakhstan","KE":"Kenya","KI":"Kiribati","KP":"Korea, Democratic People\'s Republic Of","KR":"Korea, Republic Of","KW":"Kuwait","KG":"Kyrgyzstan","LA":"Lao People\'s Democratic Republic","LV":"Latvia","LB":"Lebanon","LS":"Lesotho","LR":"Liberia","LY":"Libya","LI":"Liechtenstein","LT":"Lithuania","LU":"Luxembourg","MO":"Macao","MK":"Macedonia, The Former Yugoslav Republic Of","MG":"Madagascar","MW":"Malawi","MY":"Malaysia","MV":"Maldives","ML":"Mali","MT":"Malta","MH":"Marshall Islands","MQ":"Martinique","MR":"Mauritania","MU":"Mauritius","YT":"Mayotte","MX":"Mexico","FM":"Micronesia, Federated States Of","MD":"Moldova, Republic Of","MC":"Monaco","MN":"Mongolia","ME":"Montenegro","MS":"Montserrat","MA":"Morocco","MZ":"Mozambique","MM":"Myanmar","NA":"Namibia","NR":"Nauru","NP":"Nepal","NL":"Netherlands","NC":"New Caledonia","NZ":"New Zealand","NI":"Nicaragua","NE":"Niger","NG":"Nigeria","NU":"Niue","NF":"Norfolk Island","MP":"Northern Mariana Islands","NO":"Norway","OM":"Oman","PK":"Pakistan","PW":"Palau","PS":"Palestinian Territory, Occupied","PA":"Panama","PG":"Papua New Guinea","PY":"Paraguay","PE":"Peru","PH":"Philippines","PN":"Pitcairn","PL":"Poland","PT":"Portugal","PR":"Puerto Rico","QA":"Qatar","RO":"Romania","RU":"Russian Federation","RW":"Rwanda","RE":"R\u00c9union","BL":"Saint Barth\u00c9lemy","SH":"Saint Helena, Ascension And Tristan Da Cunha","KN":"Saint Kitts And Nevis","LC":"Saint Lucia","MF":"Saint Martin (french Part)","PM":"Saint Pierre And Miquelon","VC":"Saint Vincent And The Grenadines","WS":"Samoa","SM":"San Marino","ST":"Sao Tome And Principe","SA":"Saudi Arabia","SN":"Senegal","RS":"Serbia","SC":"Seychelles","SL":"Sierra Leone","SG":"Singapore","SX":"Sint Maarten (dutch Part)","SK":"Slovakia","SI":"Slovenia","SB":"Solomon Islands","SO":"Somalia","ZA":"South Africa","GS":"South Georgia And The South Sandwich Islands","SS":"South Sudan","ES":"Spain","LK":"Sri Lanka","SD":"Sudan","SR":"Suriname","SJ":"Svalbard And Jan Mayen","SZ":"Swaziland","SE":"Sweden","CH":"Switzerland","SY":"Syrian Arab Republic","TW":"Taiwan, Province Of China","TJ":"Tajikistan","TZ":"Tanzania, United Republic Of","TH":"Thailand","TL":"Timor-leste","TG":"Togo","TK":"Tokelau","TO":"Tonga","TT":"Trinidad And Tobago","TN":"Tunisia","TR":"Turkey","TM":"Turkmenistan","TC":"Turks And Caicos Islands","TV":"Tuvalu","UG":"Uganda","UA":"Ukraine","AE":"United Arab Emirates","GB":"United Kingdom","US":"United States","UM":"United States Minor Outlying Islands","UY":"Uruguay","UZ":"Uzbekistan","VU":"Vanuatu","VE":"Venezuela, Bolivarian Republic Of","VN":"Viet Nam","VG":"Virgin Islands, British","VI":"Virgin Islands, U.s.","WF":"Wallis And Futuna","EH":"Western Sahara","YE":"Yemen","ZM":"Zambia","ZW":"Zimbabwe","AX":"\u00c5land Islands"};

						var origin = ' . json_encode($from) . ';
						var destination = ' . json_encode($to) . ';

						var codes = {"301":"301 Permanently Redirect", "302":"302 Temporary Redirect"};

						var $ul = $("<ul />");
						var $country = $(\'<select name="countryCode[]" style="width:150px" />\').on("change", function(){
							var row = 0;
							var check = $(this).val() +  $(this).parent().next().children().val();
							$(\'select[name="countryCode[]"]\').each(function(){
								var x = $(this).val() + $(this).parent().next().children().val();
								if(x == check){
									row++;
								}
							});

							if(row > 1){
								alert("You cannot redirect same country with same page/post.");
								$(this).val("");
							}
						});

						var $url = $(\'<input type="text" name="url[]" value="\' + url + \'" style="visibility:hidden;width:250px" />\')
							.on(\'blur\', function(){
								if($(this).val() != "" && !$(this).val().match(/^http/)) {
									$(this).val(\'http://\' + $(this).val());
								}
							});

						if(url != ""){
							$url.css("visibility", "visible");
						}

						var $from = $(\'<select name="from[]" style="width:150px" />\').on("change", function(){
							var row = 0;
							var check = $(this).val() +  $(this).parent().prev().children().val();
							$(\'select[name="from[]"]\').each(function(){
								var x = $(this).val() + $(this).parent().prev().children().val();
								if(x == check){
									row++;
								}
							});

							if(row > 1){
								alert("You cannot redirect same country with same page/post.");
								$(this).val("");
								return;
							}

							if($(this).val() == $to.val()){
								alert("Source and destination cannot be same.");
								$(this).val("");
							}
						});

						var $to = $(\'<select name="to[]" style="width:150px" />\').on("change", function(){
							if($(this).val() == $from.val()){
								alert("Source and destination cannot be same.");
								$(this).val("");
								return;
							}

							$url.css("visibility", "hidden");

							if($(this).val() == "url"){
								$url.css("visibility", "visible");
							}
						});

						var $a = $(\'<a href="javascript:;">\');

						$.each(countries, function(cc, cn){
							$country.append(\'<option value="\' + cc + \'"\' + ((cc == countryCode) ? \' selected\' : \'\') + \'>\' + cn + \'</option>\');
						});

						$.each(origin, function(i, row){
							$from.append(\'<option value="\' + row[0] + \'"\' + ((row[0] == from) ? \' selected\' : \'\') + \'>\' + row[1] + \'</option>\');
						});

						$.each(destination, function(i, row){
							$to.append(\'<option value="\' + row[0] + \'"\' + ((row[0] == to) ? \' selected\' : \'\') + \'>\' + row[1] + \'</option>\');
						});

						$code = $(\'<select name="statusCode[]" style="width:200px" />\');

						$.each(codes, function(sc, sn){
							$code.append(\'<option value="\' + sc + \'"\' + ((sc == statusCode) ? \' selected\' : \'\') + \'>\' + sn + \'</option>\');
						});

						$ul.append($("<li>").append($country)).append($("<li>").append($from)).append($("<li>").append($to)).append($("<li>").append($url)).append($("<li>").append($code).append($a));

						if($("#redirection").html() == ""){
							$("#redirection").html(\'<ul><li style="width:155px"><label>Country<label></li><li style="width:150px"><label>From Page/Post<label></li><li style="width:410px"><label>Destination<label></li><li><label>Redirection Code<label></li></ul>\');
						}

						$("#redirection").append($ul);

						$(\'#redirection a\').each(function(){
							$(this)
								.html(\'<img src="' . plugins_url( 'images/delete.png', __FILE__ ) . '" width="32" height="32" align="absMiddle" />\')
								.off(\'click\')
								.on(\'click\', function(){
									$(this).parent().parent().remove();
								});
						});

						if($ul.is(\':last-child\')){
							$a.html(\'<img src="' . plugins_url( 'images/add.png', __FILE__ ) . '" width="32" height="32" align="absMiddle" />\').off(\'click\').on(\'click\', function(){
								addRow("", "", "", "", 301);
							});
						}
					}
				})( jQuery );
			</script>

			<style>
				#redirection ul{list-style:none;display:block;margin:0;padding:0}
				#redirection ul:after{content:"";display:block;clear:both}
				#redirection ul li{float:left;margin-right:10px}
				#redirection ul li label{font-weight:bold}
			</style>

			<div class="wrap">
				<h2>IP2Location Redirection</h2>
				<p>
					IP2Location Redirection allows user to easily redirect visitors predefined location based on their country.
				</p>

				<p>&nbsp;</p>

				<div style="border-bottom:1px solid #ccc;">
					<h3>Lookup Mode</h3>
				</div>

				' . $mode_status . '

				<form id="form-lookup-mode" method="post">
					<p>
						<label><input id="use-bin" type="radio" name="lookupMode" value="bin"' . ( ( $lookup_mode == 'bin' ) ? ' checked' : '' ) . '> Local BIN database</label>

						<div id="bin-mode" style="margin-left:50px;display:none;background:#d7d7d7;padding:20px">
							<p>
								BIN file download: <a href="http://www.ip2location.com/?r=wordpress" target="_blank">IP2Location Commercial database</a> | <a href="http://lite.ip2location.com/?r=wordpress" targe="_blank">IP2Location LITE database (free edition)</a>.
							</p>';

						if ( !file_exists( IP2LOCATION_REDIRECTION_ROOT . get_option( 'ip2location_redirection_database' ) ) ) {
							echo '
							<div id="message" class="error">
								<p>
									Unable to find the IP2Location BIN database! Please download the database at at <a href="http://www.ip2location.com/?r=wordpress" target="_blank">IP2Location commercial database</a> | <a href="http://lite.ip2location.com/?r=wordpress" target="_blank">IP2Location LITE database (free edition)</a>.
								</p>
							</div>';
						}
						else {
							echo '
							<p>
								<b>Current Database Version: </b>
								' . date( 'F Y', filemtime( IP2LOCATION_REDIRECTION_ROOT . get_option( 'ip2location_redirection_database' ) ) ) . '
							</p>';

							if ( filemtime( IP2LOCATION_REDIRECTION_ROOT . get_option( 'ip2location_redirection_database' ) ) < strtotime( '-2 months' ) ) {
								echo '
								<div style="background:#fff;padding:2px 10px;border-left:3px solid #cc0000">
									<p>
										<strong>REMINDER</strong>: Your IP2Location database was outdated. Please download the latest version for accurate result.
									</p>
								</div>';
							}
						}

						echo '
							<p>&nbsp;</p>

							<div style="border-bottom:1px solid #ccc;">
								<h4>Download BIN Database</h4>
							</div>

							<div id="download-status" style="margin:10px 0;"></div>

							<strong>Product Code</strong>:
							<select id="productCode" type="text" value="" style="margin-right:10px;" >
								<option value="DB1LITEBIN">DB1LITEBIN</option>
								<option value="DB1BIN">DB1BIN</option>
								<option value="DB1LITEBINIPV6">DB1LITEBINIPV6</option>
								<option value="DB1BINIPV6">DB1BINIPV6</option>
							</select>

							<strong>Email</strong>:
							<input id="username" type="text" value="" style="margin-right:10px;" />

							<strong>Password</strong>:
							<input id="password" type="password" value="" style="margin-right:10px;" />

							<button id="download" class="button action">Download</button>

							<span style="display:block; font-size:0.8em">Enter the product code, i.e, DB1LITEBIN, (the code in square bracket on your license page) and login credential for the download.</span>

							<div style="margin-top:20px;">
								<strong>Note</strong>: If you failed to download the BIN database using this automated downloading tool, please follow the below procedures to manually update the database.
								<ol style="list-style-type:circle;margin-left:30px">
									<li>Download the BIN database at <a href="http://www.ip2location.com/?r=wordpress" target="_blank">IP2Location commercial database</a> | <a href="http://lite.ip2location.com/?r=wordpress" target="_blank">IP2Location LITE database (free edition)</a>.</li>
									<li>Decompress the zip file and update the BIN database to /wp-content/plugins/ip2location-redirection/.</li>
									<li>Once completed, please refresh the information by reloading the setting page.</li>
								</ol>
							</div>
							<p>&nbsp;</p>
						</div>
					</p>
					<p>
						<label><input id="use-ws" type="radio" name="lookupMode" value="ws"' . ( ( $lookup_mode == 'ws' ) ? ' checked' : '' ) . '> IP2Location Web Service</label>

						<div id="ws-mode" style="margin-left:50px;display:none;background:#d7d7d7;padding:20px">
							<p>Please insert your IP2Location <a href="http://www.ip2location.com/web-service" target="_blank">Web service</a> API key.</p>
							<p>
								<strong>API Key</strong>:
								<input name="apiKey" type="text" value="' . $api_key . '" style="margin-right:10px;" />
							</p>
						</div>
					</p>
					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"  />
					</p>
				</form>

				<p>&nbsp;</p>

				<div style="border-bottom:1px solid #ccc;">
					<h3>Site Redirection</h3>
				</div>
				' . $redirection_status . '
				<form id="form-redirection" method="post">
					<p>
						<input type="checkbox" id="enable-redirection" name="enableRedirection"' . (($enabled) ? ' checked' : '') . ' /><label for="enable-redirection">Enable Redirection</label>
					</p>

					<div id="redirection"></div>
					<div style="clear:both"></div>
					<p>
						<a href="javascript:;" id="remove-all"><img src="' . plugins_url( 'images/page-delete.png', __FILE__ ) . '" width="32" height="32" align="absMiddle" />Remove All</a>
					</p>
					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"  />
					</p>
				</form>

				<p>&nbsp;</p>

				<div style="border-bottom:1px solid #ccc;">
					<h3 id="ip-lookup">Query IP</h3>
				</div>
				<p>
					Enter a valid IP address for checking.
				</p>';

			$ipAddress = ( isset( $_POST['ipAddress'] ) ) ? $_POST['ipAddress'] : '';

			if ( isset( $_POST['lookup'] ) ) {
				if ( !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
					echo '
					<div id="message" class="error">
						<p><strong>ERROR</strong>: Invalid IP address.</p>
					</div>';
				}
				else {
					$response = $this->get_location( $ipAddress );

					if ( $response['countryName'] ) {
						if ( $response['countryCode'] != '??' && strlen( $response['countryCode'] ) == 2 ) {
							echo '
							<div id="message" class="updated">
								<p>IP address <strong>' . $ipAddress . '</strong> belongs to <strong>' . $response['countryName'] . '</strong>.</p>
							</div>';
						}
						else{
							echo '
							<div id="message" class="error">
								<p><strong>ERROR</strong>: ' . $response['countryName'] . '</p>
							</div>';
						}
					}
					else{
						echo '
						<div id="message" class="error">
							<p><strong>ERROR</strong>: This record is not supported with this databaase.</p>
						</div>';
					}
				}
			}

			echo '
				<form action="#ip-lookup" method="post">
					<p>
						<label><b>IP Address: </b></label>
						<input type="text" name="ipAddress" value="' . $ipAddress . '" />
						<input type="submit" name="lookup" value="Lookup" class="button action" />
					</p>
				</form>

				<p>&nbsp;</p>
			</div>';
		}
	}

	function redirect() {
		if ( !get_option( 'ip2location_redirection_enabled' ) ) {
			return;
		}

		if ( !session_id() ) {
			session_start();
		}

		if( isset( $_SESSION['ip2location_redirection_redirected'] ) ) {
			unset( $_SESSION['ip2location_redirection_redirected'] );
			return;
		}

		if( !is_null( $data = json_decode( get_option( 'ip2location_redirection_rules' ) ) ) ) {
			$ipAddress = $_SERVER['REMOTE_ADDR'];

			if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
				$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
			}

			$result = $this->get_location( $ipAddress );

			foreach( $data as $values ) {
				if ( $result['countryCode'] == $values[0] ) {
					$_SESSION['ip2location_redirection_redirected'] = true;

					// Global redirection
					if ( $values[1] == 'any' ) {
						if ( $values[2] == 'url' ) {
							header( 'Location: ' . $values[3], true, $values[4] );
							die;
						}

						list( $type, $id ) = explode( '-', $values[2] );

						header( 'Location: ' . post_permalink( $id ), true, $values[4] );
						die;
					}

					list( $type, $id ) = explode( '-', $values[1] );

					if ( $id == get_the_ID() ) {
						if ( $values[2] == 'url' ) {
							header( 'Location: ' . $values[3], true, $values[4] );
							die;
						}

						list( $type, $id ) = explode( '-', $values[2] );

						header( 'Location: ' . post_permalink( $id ), true, $values[4] );
						die;
					}
				}
			}
		}
	}

	function admin_page() {
		add_options_page( 'IP2Location Redirection', 'IP2Location Redirection', 5, 'ip2location-redirection', array( &$this, 'admin_options' ) );
	}

	function start() {
		// Make sure this plugin loaded as first priority.
		$wp_path_to_this_file = preg_replace( '/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . "/$2", __FILE__ );
		$this_plugin = plugin_basename( trim( $wp_path_to_this_file ) );
		$active_plugins = get_option( 'active_plugins' );
		$this_plugin_key = array_search( $this_plugin, $active_plugins );

		if ($this_plugin_key) {
			array_splice( $active_plugins, $this_plugin_key, 1 );
			array_unshift( $active_plugins, $this_plugin );
			update_option( 'active_plugins', $active_plugins );
		}

		add_action( 'admin_menu', array( &$this, 'admin_page' ) );
	}

	function set_defaults() {
		// Initial default settings
		update_option( 'ip2location_redirection_enabled', 1 );
		update_option( 'ip2location_redirection_lookup_mode', 'bin' );
		update_option( 'ip2location_redirection_api_key', '' );
		update_option( 'ip2location_redirection_database', '' );
		update_option( 'ip2location_redirection_rules', '[]' );

		// Find any .BIN files in current directory
		$files = scandir( IP2LOCATION_REDIRECTION_ROOT );

		foreach( $files as $file ){
			if ( strtoupper( substr( $file, -4 ) ) == '.BIN' ){
				update_option( 'ip2location_redirection_database', $file );
				break;
			}
		}
	}

	function uninstall() {
		// Remove all settings
		delete_option( 'ip2location_redirection_enabled' );
		delete_option( 'ip2location_redirection_lookup_mode' );
		update_option( 'ip2location_redirection_api_key', '' );
		delete_option( 'ip2location_redirection_database' );
		delete_option( 'ip2location_redirection_rules' );
	}

	function get_location( $ip ) {
		switch( get_option( 'ip2location_redirection_lookup_mode' ) ) {
			case 'bin':
				// Make sure IP2Location database is exist.
				if ( !is_file( IP2LOCATION_REDIRECTION_ROOT . get_option( 'ip2location_redirection_database' ) ) ) {
					return false;
				}

				if ( ! class_exists( 'IP2LocationRecord' ) && ! class_exists( 'IP2Location' ) ) {
					require_once( IP2LOCATION_REDIRECTION_ROOT . 'ip2location.class.php' );
				}

				// Create IP2Location object.
				$geo = new IP2Location( IP2LOCATION_REDIRECTION_ROOT . get_option( 'ip2location_redirection_database' ) );

				// Get geolocation by IP address.
				$response = $geo->lookup( $ip );

				return array(
					'countryCode' => $response->countryCode,
					'countryName' => $response->countryName,
				);
			break;

			case 'ws':
				if ( !class_exists( 'WP_Http' ) ) {
					include_once( ABSPATH . WPINC . '/class-http.php' );
				}

				$request = new WP_Http();
				$response = $request->request( 'http://api.ip2location.com/?' . http_build_query( array(
					'key' => get_option( 'ip2location_redirection_api_key' ),
					'ip' => $ip,
				) ) , array( 'timeout' => 3 ) );

				return array(
					'countryCode' => $response['body'],
					'countryName' => $this->get_country_name( $response['body'] ),
				);
			break;
		}
	}

	function download() {
		try {
			$productCode = ( isset( $_POST['productCode'] ) ) ? $_POST['productCode'] : '';
			$username = ( isset( $_POST['username'] ) ) ? $_POST['username'] : '';
			$password = ( isset( $_POST['password'] ) ) ? $_POST['password']: '';

			if ( !class_exists( 'WP_Http' ) ) {
				include_once( ABSPATH . WPINC . '/class-http.php' );
			}

			// Remove existing database.zip.
			if ( file_exists( IP2LOCATION_REDIRECTION_ROOT . 'database.zip' ) ) {
				@unlink( IP2LOCATION_REDIRECTION_ROOT . 'database.zip' );
			}

			// Start downloading BIN database from IP2Location website.
			$request = new WP_Http();
			$response = $request->request( 'http://www.ip2location.com/download?' . http_build_query( array(
				'productcode' => $productCode,
				'login' => $username,
				'password' => $password,
			) ) , array( 'timeout' => 120 ) );

			if ( ( isset( $response->errors ) ) || ( !( in_array( '200', $response['response'] ) ) ) ) {
				die( 'Connection error.' );
			}

			// Save downloaded package into plugin directory.
			$fp = fopen( IP2LOCATION_REDIRECTION_ROOT . 'database.zip', 'w' );

			fwrite( $fp, $response['body'] );
			fclose( $fp );

			// Decompress the package.
			$zip = zip_open( IP2LOCATION_REDIRECTION_ROOT . 'database.zip' );

			if ( !is_resource( $zip ) ) {
				die('Downloaded file is corrupted.');
			}

			while( $entries = zip_read( $zip ) ) {
				// Extract the BIN file only.
				$file_name = zip_entry_name($entries);

				if ( substr( $file_name, -4 ) != '.BIN' ) {
					continue;
				}

				// Remove existing BIN files before extrac the latest BIN file.
				$files = scandir( IP2LOCATION_REDIRECTION_ROOT );

				foreach( $files as $file ){
					if ( strtoupper( substr( $file, -4 ) ) == '.BIN' ){
						@unlink( IP2LOCATION_REDIRECTION_ROOT . $file );
					}
				}

				$handle = fopen( IP2LOCATION_REDIRECTION_ROOT . $file_name, 'w+' );
				fwrite( $handle, zip_entry_read( $entries, zip_entry_filesize($entries) ) );
				fclose( $handle );

				if ( !file_exists( IP2LOCATION_REDIRECTION_ROOT . $file_name ) ) {
					die( 'ERROR' );
				}

				@unlink( IP2LOCATION_REDIRECTION_ROOT . 'database.zip' );

				die('SUCCESS');
			}
		}
		catch( Exception $e ) {
			die( 'ERROR' );
		}

		die( 'ERROR' );
	}

	function get_country_name( $code ) {
		$countries = array( 'AF' => 'Afghanistan','AL' => 'Albania','DZ' => 'Algeria','AS' => 'American Samoa','AD' => 'Andorra','AO' => 'Angola','AI' => 'Anguilla','AQ' => 'Antarctica','AG' => 'Antigua and Barbuda','AR' => 'Argentina','AM' => 'Armenia','AW' => 'Aruba','AU' => 'Australia','AT' => 'Austria','AZ' => 'Azerbaijan','BS' => 'Bahamas','BH' => 'Bahrain','BD' => 'Bangladesh','BB' => 'Barbados','BY' => 'Belarus','BE' => 'Belgium','BZ' => 'Belize','BJ' => 'Benin','BM' => 'Bermuda','BT' => 'Bhutan','BO' => 'Bolivia','BA' => 'Bosnia and Herzegovina','BW' => 'Botswana','BV' => 'Bouvet Island','BR' => 'Brazil','IO' => 'British Indian Ocean Territory','BN' => 'Brunei Darussalam','BG' => 'Bulgaria','BF' => 'Burkina Faso','BI' => 'Burundi','KH' => 'Cambodia','CM' => 'Cameroon','CA' => 'Canada','CV' => 'Cape Verde','KY' => 'Cayman Islands','CF' => 'Central African Republic','TD' => 'Chad','CL' => 'Chile','CN' => 'China','CX' => 'Christmas Island','CC' => 'Cocos (Keeling) Islands','CO' => 'Colombia','KM' => 'Comoros','CG' => 'Congo','CK' => 'Cook Islands','CR' => 'Costa Rica','CI' => 'Cote D\'Ivoire','HR' => 'Croatia','CU' => 'Cuba','CY' => 'Cyprus','CZ' => 'Czech Republic','CD' => 'Democratic Republic of Congo','DK' => 'Denmark','DJ' => 'Djibouti','DM' => 'Dominica','DO' => 'Dominican Republic','TP' => 'East Timor','EC' => 'Ecuador','EG' => 'Egypt','SV' => 'El Salvador','GQ' => 'Equatorial Guinea','ER' => 'Eritrea','EE' => 'Estonia','ET' => 'Ethiopia','FK' => 'Falkland Islands (Malvinas)','FO' => 'Faroe Islands','FJ' => 'Fiji','FI' => 'Finland','FR' => 'France','FX' => 'France, Metropolitan','GF' => 'French Guiana','PF' => 'French Polynesia','TF' => 'French Southern Territories','GA' => 'Gabon','GM' => 'Gambia','GE' => 'Georgia','DE' => 'Germany','GH' => 'Ghana','GI' => 'Gibraltar','GR' => 'Greece','GL' => 'Greenland','GD' => 'Grenada','GP' => 'Guadeloupe','GU' => 'Guam','GT' => 'Guatemala','GN' => 'Guinea','GW' => 'Guinea-bissau','GY' => 'Guyana','HT' => 'Haiti','HM' => 'Heard and Mc Donald Islands','HN' => 'Honduras','HK' => 'Hong Kong','HU' => 'Hungary','IS' => 'Iceland','IN' => 'India','ID' => 'Indonesia','IR' => 'Iran (Islamic Republic of)','IQ' => 'Iraq','IE' => 'Ireland','IL' => 'Israel','IT' => 'Italy','JM' => 'Jamaica','JP' => 'Japan','JO' => 'Jordan','KZ' => 'Kazakhstan','KE' => 'Kenya','KI' => 'Kiribati','KR' => 'Korea, Republic of','KW' => 'Kuwait','KG' => 'Kyrgyzstan','LA' => 'Lao People\'s Democratic Republic','LV' => 'Latvia','LB' => 'Lebanon','LS' => 'Lesotho','LR' => 'Liberia','LY' => 'Libyan Arab Jamahiriya','LI' => 'Liechtenstein','LT' => 'Lithuania','LU' => 'Luxembourg','MO' => 'Macau','MK' => 'Macedonia','MG' => 'Madagascar','MW' => 'Malawi','MY' => 'Malaysia','MV' => 'Maldives','ML' => 'Mali','MT' => 'Malta','MH' => 'Marshall Islands','MQ' => 'Martinique','MR' => 'Mauritania','MU' => 'Mauritius','YT' => 'Mayotte','MX' => 'Mexico','FM' => 'Micronesia, Federated States of','MD' => 'Moldova, Republic of','MC' => 'Monaco','MN' => 'Mongolia','MS' => 'Montserrat','MA' => 'Morocco','MZ' => 'Mozambique','MM' => 'Myanmar','NA' => 'Namibia','NR' => 'Nauru','NP' => 'Nepal','NL' => 'Netherlands','AN' => 'Netherlands Antilles','NC' => 'New Caledonia','NZ' => 'New Zealand','NI' => 'Nicaragua','NE' => 'Niger','NG' => 'Nigeria','NU' => 'Niue','NF' => 'Norfolk Island','KP' => 'North Korea','MP' => 'Northern Mariana Islands','NO' => 'Norway','OM' => 'Oman','PK' => 'Pakistan','PW' => 'Palau','PA' => 'Panama','PG' => 'Papua New Guinea','PY' => 'Paraguay','PE' => 'Peru','PH' => 'Philippines','PN' => 'Pitcairn','PL' => 'Poland','PT' => 'Portugal','PR' => 'Puerto Rico','QA' => 'Qatar','RE' => 'Reunion','RO' => 'Romania','RU' => 'Russian Federation','RW' => 'Rwanda','KN' => 'Saint Kitts and Nevis','LC' => 'Saint Lucia','VC' => 'Saint Vincent and the Grenadines','WS' => 'Samoa','SM' => 'San Marino','ST' => 'Sao Tome and Principe','SA' => 'Saudi Arabia','SN' => 'Senegal','SC' => 'Seychelles','SL' => 'Sierra Leone','SG' => 'Singapore','SK' => 'Slovak Republic','SI' => 'Slovenia','SB' => 'Solomon Islands','SO' => 'Somalia','ZA' => 'South Africa','GS' => 'South Georgia And The South Sandwich Islands','ES' => 'Spain','LK' => 'Sri Lanka','SH' => 'St. Helena','PM' => 'St. Pierre and Miquelon','SD' => 'Sudan','SR' => 'Suriname','SJ' => 'Svalbard and Jan Mayen Islands','SZ' => 'Swaziland','SE' => 'Sweden','CH' => 'Switzerland','SY' => 'Syrian Arab Republic','TW' => 'Taiwan','TJ' => 'Tajikistan','TZ' => 'Tanzania, United Republic of','TH' => 'Thailand','TG' => 'Togo','TK' => 'Tokelau','TO' => 'Tonga','TT' => 'Trinidad and Tobago','TN' => 'Tunisia','TR' => 'Turkey','TM' => 'Turkmenistan','TC' => 'Turks and Caicos Islands','TV' => 'Tuvalu','UG' => 'Uganda','UA' => 'Ukraine','AE' => 'United Arab Emirates','GB' => 'United Kingdom','US' => 'United States','UM' => 'United States Minor Outlying Islands','UY' => 'Uruguay','UZ' => 'Uzbekistan','VU' => 'Vanuatu','VA' => 'Vatican City State (Holy See)','VE' => 'Venezuela','VN' => 'Viet Nam','VG' => 'Virgin Islands (British)','VI' => 'Virgin Islands (U.S.)','WF' => 'Wallis and Futuna Islands','EH' => 'Western Sahara','YE' => 'Yemen','YU' => 'Yugoslavia','ZM' => 'Zambia','ZW' => 'Zimbabwe' );

		return ( isset( $countries[$code] ) ) ? $countries[$code] : '';
	}
}
// Initial class
$ip2location_redirection = new IP2LocationRedirection();
$ip2location_redirection->start();

register_activation_hook( __FILE__, array( $ip2location_redirection, 'set_defaults' ) );
register_uninstall_hook( __FILE__, array( $ip2location_redirection, 'uninstall' ) );

add_action( 'wp_ajax_update_ip2location_redirection_database', array( $ip2location_redirection, 'download' ) );
add_action( 'wp', array( $ip2location_redirection, 'redirect' ) );
?>