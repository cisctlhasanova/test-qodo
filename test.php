<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . "/traits/LoginAccesses.php";
require_once __DIR__ . "/traits/GlobalScopeInfo.php";

class Login_model extends MY_Model {
  
	function __construct() {
		parent::__construct();
	}

/*
	* İşçi girişi
	*/
	public function action($params) {
		escapeAllKeys($params,	NULL,	$this->db);


		if (isset($params["phone"]) && $params["phone"]) {
			$where_query =  filter_var($params["phone"], FILTER_VALIDATE_EMAIL) ? "  " . column_name("users",	"email",	FALSE, TRUE) . " = '{$params["phone"]}' " : "  " . column_name("users",	"phone", FALSE, TRUE)." = '{$params["phone"]}' ";
		} else {
			$where_query = "  " . column_name("users", "id",	FALSE,	TRUE) . " = '{$params["user_id"]}' ";
		}


		if (empty($params["organization_id"])) {
			$organization_data_query = "SELECT
																				" . column_name("organizations","token",			"organization_id",	TRUE) . ",
																				" . column_name("organizations","blocked", 		TRUE, 							TRUE) . ",
																				" . column_name("organizations","is_active", 	TRUE, 							TRUE) . "
																	 FROM `" . main_table_name("employees") . "`
																	 LEFT JOIN `" . main_table_name("organizations") . "` ON " . column_name("employees",	"organization_id",	FALSE,	TRUE) . " = " . column_name("organizations",	"id",	FALSE,	TRUE) . "
																	 								AND " . column_name("organizations", "is_deleted", FALSE, TRUE) . " = 0
																	 LEFT JOIN `" . main_table_name("users") . "` ON " . column_name("users","id",FALSE,TRUE) . " = " . column_name("employees","user_id",FALSE,TRUE) . "
																	  						  AND " . column_name("users", "is_deleted", FALSE, TRUE) . " = 0
																	 WHERE " . column_name("employees", "is_deleted", FALSE, TRUE) . " = 0
																	 $where_query
																	 AND (" . column_name("employees","is_executive",FALSE,TRUE) . " = '" . STATUS_ACTIVE."'
																	 			OR (
																						" . column_name("organizations","is_active",FALSE,TRUE) . " = '" . STATUS_ACTIVE."' AND " . column_name("organizations","blocked",FALSE,TRUE) . " = '" . STATUS_DEACTIVE."'
																					))
																	 ORDER BY " . column_name("employees","is_active",FALSE,TRUE) . " ASC,
																						 " . column_name("employees","order",FALSE,TRUE) . " DESC,
																							 " . column_name("employees","id",FALSE,TRUE) . " ASC
																	 LIMIT 1";
			 $organization_query = $this->db->query($organization_data_query);
			 if (!$organization_query->num_rows()) {
			 	return rest_response(
					Status_codes::HTTP_NO_CONTENT,
					lang("texts.Employee entry not found")
				);
			 }
			 $organization_row = $organization_query->row_array();
			 if ($organization_row["blocked"] === STATUS_ACTIVE) {
			 		return rest_response(
						Status_codes::HTTP_UNAUTHORIZED,
						lang("texts.Organization temporarily blocked")
					);
			 }

			 if ($organization_row["is_active"] !== STATUS_ACTIVE) {
			 		return rest_response(
						Status_codes::HTTP_UNAUTHORIZED,
						lang("texts.Organization is not active")
					);
			 }
			 if (!isset($organization_row["organization_id"])) {
				 return rest_response(
	 	      Status_codes::HTTP_NO_CONTENT,
	 	      lang("texts.Employee entry not found")
	 	    );
			 }
			 $params["organization_id"] = $organization_row["organization_id"];
		}

		if ($params["switch"] ?? FALSE) {
			$os_insert_list = [
				column_name("organization_switch","token") 									=> $params["organization_id"],
				column_name("organization_switch","from_organization_id") 	=> $params["from_organization_id"],
				column_name("organization_switch","to_organization_id") 		=> $params["to_organization_id"],
				column_name("organization_switch","switcher_id") 						=> $params["switcher_id"],
				column_name("organization_switch","type") 									=> $params["switch_type"]
			];
			// log_message("error",json_encode($os_insert_list));

			// DEBUG: PID yoxdur
			$this->db->insert(main_table_name("organization_switch"),$os_insert_list);
		}

		$sql_query = "SELECT
	                       ".column_name("employees",				"id",										TRUE,												TRUE, md5: TRUE) . ",
												 ".column_name("currencies",			"id",										"currency_id",							TRUE, md5: TRUE) . ",
												 ".column_name("employees",				"blob_id",							TRUE,												TRUE, is_uuid: TRUE).",
												 ".column_name("currencies",			"name",									"currency_name",						TRUE).",
												 ".column_name("currencies",			"full_name",						"currency_full_name",				TRUE).",
												 ".column_name("employees",				"id",										"real_id",									TRUE).",
												 ".column_name("users",						"id",										"user_id",									TRUE).",
												 ".column_name("users",						"password",							TRUE,												TRUE).",
												 ".column_name("users",						"verified",							TRUE,												TRUE).",
												 ".column_name("employees",				"role_id",							TRUE,												TRUE).",
												 ".column_name("employees",				"lang_id",							TRUE,												TRUE).",
												 ".column_name("employee_roles",	"name",									"role_name",								TRUE).",
												 ".column_name("employee_roles",	"working_hours_start",	"role_working_hours_start",	TRUE).",
												 ".column_name("employee_roles",	"working_hours_end",		"role_working_hours_end",		TRUE).",
												 ".column_name("employees",				"code",									TRUE,												TRUE).",
												 ".column_name("employees",				"pin_code",							TRUE,												TRUE).",
												 ".column_name("employees",				"name",									TRUE,												TRUE).",
												 ".column_name("employees",				"surname",							TRUE,												TRUE).",
												 ".column_name("employees",				"birthdate",						TRUE,												TRUE).",
												 ".column_name("employees",				"email",								TRUE,												TRUE).",
												 ".column_name("employees",				"phone",								TRUE,												TRUE).",
												 ".column_name("employees",				"created_at",						"creation_date",						TRUE).",
												 ".column_name("users",						"email_verified",				TRUE,												TRUE).",
												 ".column_name("users",						"phone_verified",				TRUE,												TRUE).",
												 ".column_name("users",						"phone",								"user_phone",								TRUE).",
												 ".column_name("employees",				"is_active",						TRUE,												TRUE).",
												 ".column_name("employees",				"default_warehouse_id",	TRUE,												TRUE).",
												 ".column_name("employees",				"default_branch_id",		TRUE,												TRUE).",
												 ".column_name("employees",				"organization_id",			TRUE,												TRUE).",
												 IFNULL(".column_name("organizations","parent_organization_id",FALSE,									TRUE).",
												 ".column_name("employees",				"organization_id",			FALSE,											TRUE).") AS main_organization_id,
	                       ".column_name("employees",				"is_executive",					TRUE,												TRUE).",
												 ".column_name("organizations",		"name",									"organization",							TRUE).",
												 ".column_name("organizations",		"code",									"organization_code",				TRUE).",
												 ".column_name("organizations",		"currency",							"organization_currency",		TRUE).",
												 ".column_name("organizations",		"created_at",						"organization_created_at",	TRUE).",
	                       ".column_name("organizations",		"avatar",								"organization_logo",				TRUE).",
												 ".column_name("organizations",		"blocked",							"organization_blocked",			TRUE).",
												 ".column_name("organizations",		"token",								"organization_token",				TRUE).",
												 ".column_name("users",						"is_developer",					TRUE,												TRUE).",
												 (SELECT
												 			 COUNT(1)
													FROM `"	.	main_table_name("warehouses")	.	"`
													WHERE " . column_name("warehouses", "is_deleted", 			FALSE, 	TRUE) . " = 0
													AND 	"	.	column_name("warehouses",	"organization_id",	FALSE,	TRUE)	.	" = "	.	column_name("organizations",	"id",	FALSE,	TRUE)	.	") as warehouse_count,
												 " . column_name("users", "avatar", "user_avatar",TRUE) . ",

												 ".column_name("currencies",	"id",				 "secondary_currency_id",				 "secondary_currency", md5: TRUE) . ",
												 ".column_name("currencies",  "name",			 "secondary_currency_name",			 "secondary_currency").",
												 ".column_name("currencies",  "full_name", "secondary_currency_full_name", "secondary_currency")."

									FROM  `" . main_table_name("users") . "`
									LEFT JOIN `" . main_table_name("employees") . "` ON ".column_name("employees","user_id",FALSE,TRUE)." = ".column_name("users","id",FALSE,TRUE)."
									LEFT JOIN `".main_table_name("employee_roles")."` ON ".column_name("employee_roles","id",FALSE,TRUE)." = ".column_name("employees","role_id",FALSE,TRUE)."
																AND " . column_name("employee_roles", "is_deleted", FALSE, TRUE) . " = 0
									LEFT JOIN `" . main_table_name("organizations") . "` ON ".column_name("employees","organization_id",FALSE,TRUE)." = ".column_name("organizations","id",FALSE,TRUE)."
																AND " . column_name("organizations", "is_deleted", FALSE, TRUE) . " = 0
									LEFT JOIN `" . main_table_name("currencies") . "` ON ".column_name("currencies","special_key",FALSE,TRUE)." = ".column_name("organizations","currency",FALSE,TRUE)."
																AND ".column_name("currencies",	"organization_id",	FALSE,	TRUE)." = ".column_name("organizations","id",FALSE,TRUE)."
																	AND ".column_name("currencies",	"is_deleted",	FALSE,	TRUE)." = 0
									LEFT JOIN `" . main_table_name("currencies") . "` `secondary_currency`
														  ON ".column_name("currencies","special_key",FALSE,"secondary_currency")." = CONCAT('0x', HEX(".column_name("organizations","secondary_currency",FALSE,TRUE)."))
																AND " . column_name("currencies", "special_key", FALSE, TRUE) . " IS NOT NULL
																	AND ".column_name("currencies",	"organization_id", FALSE,	"secondary_currency")." = ".column_name("organizations","id",FALSE,TRUE)."
																		AND ".column_name("currencies",	"is_deleted",	FALSE,	"secondary_currency")." = 0
									WHERE $where_query
									AND 	" . column_name("organizations",	"token",FALSE,TRUE)." = '{$params["organization_id"]}'
									AND 	" . column_name("employees",			"is_active",FALSE,TRUE)." = '" . STATUS_ACTIVE . "'
									AND 	(".column_name("organizations",		"is_active",FALSE,TRUE)." = '" . STATUS_ACTIVE . "' OR ".column_name("employees","is_executive",FALSE,TRUE)." = '" . STATUS_ACTIVE . "')
									AND 	" . column_name("users",					"is_active",	FALSE,	TRUE)." = '" . STATUS_ACTIVE . "'
									AND 	" . column_name("employees",			"id",	FALSE,	TRUE)." IS NOT NULL
									AND 	" . column_name("users", 					"is_deleted", FALSE, TRUE) . " = 0
									ORDER BY ".column_name("employees",			"order",	FALSE,	TRUE)." ASC,
														".column_name("employees","id",	FALSE,	TRUE)." ASC
	                LIMIT 1";

	  $employee_query = $this->db->query($sql_query);

	  if (!$employee_query->num_rows()) {
	    return rest_response(
	      Status_codes::HTTP_NO_CONTENT,
	      lang("texts.Employee entry not found")
	    );
	  }


	  $employee_row  				= $employee_query->row_array();
		$employee_row["code"] = customDocumentCode(type: "employee", code: $employee_row["code"]);

		if ($employee_row["organization_blocked"] === STATUS_ACTIVE) {
	 	 return rest_response(
	 		 Status_codes::HTTP_UNAUTHORIZED,
	 		 lang("texts.Employee entry not found")
	 	 );
	  }


		if ($params["phone"] ?? NULL) {

			$attempt_date  = now(NULL, NULL, $this->config->item("max_login_interval"));
			$attempt_query = "SELECT
														COUNT(1) as count
												FROM `".main_table_name("user_login_attempts")."`
												WHERE " . column_name("user_login_attempts",	"phone",				FALSE,	TRUE)	.	" = '{$params["phone"]}'
												AND 	" . column_name("user_login_attempts",	"created_at",		FALSE,	TRUE)	.	" > '$attempt_date'
												AND 	" . column_name("user_login_attempts",	"status",				FALSE,	TRUE)	.	" = '" . special_codes("users.login_attempts.status.failed") . "'
												AND 	" . column_name("user_login_attempts",	"is_deleted", 	FALSE, 	TRUE) . " = 0
												";
			$attempt_query 	= $this->db->query($attempt_query);
			$attempt_result = $attempt_query->row_array();
			if ($attempt_result["count"] > $this->config->item("max_login_attempt")) {
				$location_details = $this->getLocationDetails($params);

				$list = [
          "name" 						=> "too_many_login_attempts", //special_codes("notifications.types.too_many_login_attempts"),
          "user_id" 				=> NULL,
          "employee_id" 		=> $employee_row["real_id"],
          "user_email" 			=> NULL,
          "organization_id" => $employee_row["main_organization_id"],
          "body" 						=> [
            "phone" 		=> $employee_row["phone"],
            "name" 			=> $employee_row["name"],
						"city" 			=> $location_details["city"] 			?? lang("app.Unknown"),
						"country" 	=> $location_details["country"] 	?? lang("app.Unknown")
          ]
        ];

				$this->load->library("Notify");
        Notify::send($list);

				return rest_response(
					Status_codes::HTTP_ALREADY_REPORTED,
					lang("texts.Too many login attempts")
				);
			}
		}

		if ($employee_row["is_active"] !== STATUS_ACTIVE) {
	    return rest_response(
	      Status_codes::HTTP_MOVED_TEMPORARILY,
	      lang("texts.Employee entry temporarily closed")
	    );
	  }

		if ($params && !$params["password_free"]) {
			if (str_starts_with($employee_row["password"], "0x")) {
				$employee_row["password"] = Content::decrypt_blob($employee_row["password"], $params["password"]);
			}
			if (!password_verify($params["password"], $employee_row["password"])) {
				$switch_insert = [
					column_name("user_login_attempts","user_ip") 					=> $params["user_ip"],
					column_name("user_login_attempts","user_agent")				=> $params["user_agent"] ?? NULL,
					column_name("user_login_attempts","user_id") 					=> $employee_row["user_id"],
					column_name("user_login_attempts","organization_id") 	=> $employee_row["organization_id"],
					column_name("user_login_attempts","phone") 						=> $params["phone"] ?? NULL,
					column_name("user_login_attempts","status") 					=> special_codes("users.login_attempts.status.failed")
				];

				if (column_name("user_login_attempts","location_data_json")) {
					$location_details = $this->getLocationDetails($params);
					$switch_insert[column_name("user_login_attempts","location_data_json")] = json_encode($location_details);
				}

				if (column_name("user_login_attempts","session_token") && !empty($params["sess_cookie_name"])) {
					$switch_insert[column_name("user_login_attempts","session_token")] = $params["sess_cookie_name"];
				}


				// DEBUG: PID yoxdur
				$this->db->set(column_name("user_login_attempts","status_bin"), special_codes("users.login_attempts.status.failed", is_binary: TRUE));
				$this->db->insert(main_table_name("user_login_attempts"), $switch_insert);

				// Main section to return process if password is wrong
				return rest_response(
					Status_codes::HTTP_BAD_REQUEST,
					lang("texts.Phone or password is wrong")
				);
			}
		}

		if ($employee_row["verified"] === STATUS_DEACTIVE) {
			return rest_response(
				Status_codes::HTTP_PARTIAL_CONTENT,
				lang("texts.User is not verified, yet")
			);
		}


		$is_developer = $employee_row["is_developer"] === STATUS_ACTIVE;


		$organizations = $this->getOrganizations([
			"emp_id" 						=> $employee_row["id"],
			"user_id" 					=> $employee_row["user_id"],
			"is_developer" 			=> $is_developer,
			"organization_id" 	=> $params["organization_id"],
			"code" 							=> $employee_row["organization_code"],
			"emp_phone" 				=> $employee_row["phone"]
		]);

		if (isset($organizations["code"])) {
			return $organizations;
		}

		$organizations_features = Auth::features([
			"smodule"						=> $params["smodule"],
			"database_token"		=> $params["database_token"],
			"organization_id" 	=> $employee_row["main_organization_id"],
			"is_developer" 			=> $is_developer,
		]);

		$this->new_domain_token = $organizations_features["new_domain_token"];
		$this->is_owned 				= $organizations_features["is_owned"];


	  $remember_me_token = $this->getRememberMeToken([
			"remember_me" 			=> $params["remember_me"],
			"user_ip" 					=> $params["user_ip"],
			"is_developer" 			=> $is_developer,
			"user_id" 					=> $employee_row["user_id"],
			"user_agent" 				=> $params["user_agent"] 			?? NULL,
			"pid" 							=> $params["pid"],
			"previous_token" 		=> $params["previous_token"] 	?? NULL
		]);

		$privileges = NULL;

		if (!$is_developer && $employee_row["role_id"]) {
			$cache_key = cacheKeyGenerator($employee_row["organization_id"], $this->new_domain_token, "org-privs", NULL, NULL, $employee_row["role_id"]);

			$privileges = Filecaching::get($cache_key);
			if (!$privileges) {
				$this->load->model("organizations/employees/roles/Privileges_model", "priv_model");
				$privileges = $this->priv_model->getAll([
					"organization_id" 	=> [
						"id" => $is_developer ? $params["organization_id"] : $employee_row["main_organization_id"]
					],
					"role_id" 					=> $employee_row["role_id"],
					"local_call" 				=> TRUE
				]);
				// if ($privileges) {
					Filecaching::set($cache_key,	$privileges);
				// }
			}
			$privileges = $cache_key;
		}

		$configs 				= $this->getConfigs([
			"is_developer" 		=> $is_developer,
			"cache"						=> TRUE,
			"ignoreCache"			=> TRUE,
			"organization_id" => $employee_row["organization_id"],
			"database_token" 	=> $params["database_token"]
		]);


		$warehouse_list	= [];

		$branch_list 		= [];


		// getting customer debt amount and also getting remote organization id
		$this->load->library("services/RegOrg");
		$reg_org_debt_details 							= RegOrg::getDebtAmount($params);
		$this->registered_organization_id 	= $reg_org_debt_details["registered_organization_id"];
		$this->last_operation_date					= $reg_org_debt_details["last_operation_date"];


		// Getting allowed modules from subscription joints
		$modules = RegOrg::getOrganizationModules([
			"is_executive" 		 					 	=> $employee_row["is_executive"],
			"is_developer"								=> $is_developer,
			"employee_id" 		 					 	=> $employee_row["real_id"],
			"registered_organization_id"	=> $this->registered_organization_id
		]);


		// $current_module = strtolower(in_array($params["smodule"], $modules) ? $params["smodule"] : "erp");
		$current_module = strtolower($params["smodule"] && $modules && in_array($params["smodule"], $modules) ? $params["smodule"] : ($modules ? $modules[0] : "erp"));


		if (in_array($current_module, ["erp"])) {
			$warehouse_list = $this->getWarehouses([
				"is_developer" 						=> $is_developer,
				"is_executive" 						=> $employee_row["is_executive"],
				"organization_id" 				=> $employee_row["main_organization_id"],
				"emp_real_id" 						=> $employee_row["real_id"]
			]);

			$branch_list = $this->getBranches([
				"is_developer" 						=> $is_developer,
				"is_executive" 						=> $employee_row["is_executive"],
				"organization_id" 				=> $employee_row["main_organization_id"],
				"param_branch_id" 				=> $params["branch_id"],
				"default_branch_id" 			=> $employee_row["default_branch_id"],
				"emp_real_id" 						=> $employee_row["real_id"]
			]);
		}





		$bnh_complexes = [];

		if(in_array("bnh", $modules) && in_array($current_module, ["bnh"])){
			$bnh_complexes = $this->getBNHComplexes([
				"is_executive" 		 					 	=> $employee_row["is_executive"],
				"is_developer"								=> $is_developer,
				"organization_id"  					 	=> $employee_row["main_organization_id"],
				"employee_id" 		 					 	=> $employee_row["real_id"],
			]);
		}

		$cashboxes_list = $this->getCashboxes([
			"is_developer" 									=> $is_developer,
			"is_executive" 									=> $employee_row["is_executive"],
			"organization_id" 							=> $employee_row["main_organization_id"],
			"employee_id" 									=> $employee_row["real_id"]
		]);



		if (!empty($params["switch"]) || !empty($params["password"])) {
			$switch_insert = [
				column_name("user_login_attempts","user_ip") 					=> $params["user_ip"],
				column_name("user_login_attempts","user_agent")				=> $params["user_agent"] ?? NULL,
				column_name("user_login_attempts","user_id") 					=> $employee_row["user_id"],
				column_name("user_login_attempts","organization_id")  => $employee_row["organization_id"],
				column_name("user_login_attempts","switch") 					=> isset($params["switch"]) && $params["switch"] ? STATUS_ACTIVE : STATUS_DEACTIVE,
				column_name("user_login_attempts","phone") 						=> $params["phone"] ?? NULL,
				column_name("user_login_attempts","status") 					=> special_codes("users.login_attempts.status.success")
			];

			if (column_name("user_login_attempts","session_token") && !empty($params["sess_cookie_name"])) {
				$switch_insert[column_name("user_login_attempts","session_token")] = $params["sess_cookie_name"];
			}

			// DEBUG: PID yoxdur
			$this->db->set(column_name("user_login_attempts","status_bin"), special_codes("users.login_attempts.status.success", is_binary: TRUE));
			$this->db->insert(main_table_name("user_login_attempts"),$switch_insert);
		}



		$currency_list = [
			"id" 		   		=> $employee_row["currency_id"],
			"name" 	   		=> $employee_row["currency_name"],
			"full_name" 	=> $employee_row["currency_full_name"]
		];

		$secondary_currency_list = $employee_row["secondary_currency_id"] ? [
			"id" 		   		=> $employee_row["secondary_currency_id"],
			"name" 	   		=> $employee_row["secondary_currency_name"],
			"full_name" 	=> $employee_row["secondary_currency_full_name"]
		] : NULL;

		if ($employee_row["organization_logo"]) {
			$employee_row["organization_logo"] = ImageContainer::set("organizations/logos" . $employee_row["organization_token"], decode_blob($employee_row["organization_logo"]));
		}

		$language = $configs["system_contents"]["system_language"] ?? NULL;
		$language = $this->getLang(lang_id: $employee_row["lang_id"], default: $language);


		$user_row = [
			"id" 													=> $employee_row["id"],
			"blob_id" 										=> $employee_row["blob_id"],
			"organization"								=> [
				"id" 														=> $employee_row["organization_token"],
				"name"													=> $this->organization_name ?: $employee_row["organization"],
				"code" 													=> $employee_row["organization_code"],
				"creation_date"									=> $employee_row["organization_created_at"],
				"logo" 													=> $employee_row["organization_logo"] ?: NULL,
				"features" 											=> $organizations_features,
				"configs" 											=> $configs,
			],
			"organizations" 							=> $organizations,
			"currency" 										=> $currency_list,
			"secondary_currency" 				  => $secondary_currency_list,
			"user_limit" 									=> $organizations_features["user_limit"],
			"is_executive" 								=> $employee_row["is_executive"] === STATUS_ACTIVE,
			"code" 												=> $employee_row["code"],
			"language" 										=> $language,
			"creation_date"								=> $employee_row["creation_date"],
			"pin_code" 										=> $employee_row["pin_code"],
			"name" 												=> $employee_row["name"],
			"surname" 										=> $employee_row["surname"],
			"birthdate" 									=> $employee_row["birthdate"],
			"email" 											=> $employee_row["email"],
			"warehouse_count" 			  		=> (float)$employee_row["warehouse_count"],
			"phone" 											=> $employee_row["user_phone"],
			"organization_created_at" 		=> $employee_row["organization_created_at"],
			"avatar" 											=> $employee_row["user_avatar"] ? ImageContainer::set("employees/avatars" . $employee_row["id"], decode_blob($employee_row["user_avatar"])) : NULL,
			"user_phone" 									=> $employee_row["user_phone"],
			"is_developer" 								=> $is_developer ?: $employee_row["is_developer"],
			"is_owned" 								 		=> $this->is_owned,
			"email_verified" 							=> $employee_row["email_verified"] === STATUS_ACTIVE,
			"phone_verified" 							=> $employee_row["phone_verified"] === STATUS_ACTIVE,
			"role" 												=> $employee_row["role_name"],
			"role_id" 										=> md5($employee_row["role_id"] ?: "NOROLEID"),
			"role_working_hours_start"		=> $employee_row["role_working_hours_start"],
			"role_working_hours_end"			=> $employee_row["role_working_hours_end"],
			"remember_me_token" 					=> $remember_me_token,
			"current_module"							=> $current_module,
			"modules" 									 	=> $modules,
			"privileges" 									=> $privileges,
			"warehouses" 									=> $warehouse_list,
			"division_count"							=> $this->division_count,

			"cashboxes"										=> $cashboxes_list,

			"branches" 										=> $branch_list,
			"branch" 											=> $this->current_branch,

			"view_as"											=> FALSE,
			"user_id" 										=> md5($employee_row["user_id"]),

			//BNH
			"bnh_complexes" 						  => $bnh_complexes,
		];

		if ($reg_org_debt_details["amount"] > 0) {
			$user_row["org_debt_amount"] = $reg_org_debt_details["amount"];
			$user_row["show_debt_popup"] = $this->last_operation_date ? time() > strtotime($this->last_operation_date . ' + 14 days') : FALSE;
		}

		return rest_response(
			Status_codes::HTTP_ACCEPTED,
			lang("app.Success"),
			$user_row
		);
	}

