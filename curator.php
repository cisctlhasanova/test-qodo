<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Curator_model extends MY_Model {

  function __construct() {
    parent::__construct()
  }

  function add($params) {
    escapeAllKeys($params, NULL, $this->db);

    // To store existsing supervisor profile
    $supervisor_profile = [];

    // Checking existence of supervisor profile if it's id or employee id that is related with it is provided
    if ($params["profile_id"] || !empty($params["employee"])) {
      $profile_id_query   =  $params["profile_id"] ? "AND " . column_name("crm_supervisor_profiles", "id",          FALSE, TRUE, md5: TRUE) . " = '{$params["profile_id"]}'"
                                                   : "AND " . column_name("crm_supervisor_profiles", "employee_id", FALSE, TRUE) . "            = '{$params["employee"]["id"]}'";

      $profile_sql_query  =  "SELECT
                                    " . column_name("crm_supervisor_profiles", "id",           TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "employee_id",  TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "name",         TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "surname",      TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "phone",        TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "phone_prefix", TRUE, TRUE) . ",
                                    " . column_name("crm_supervisor_profiles", "email",        TRUE, TRUE) . ",
                              FROM `" . main_table_name("crm_supervisor_profiles") . "`
                              WHERE " . column_name("crm_supervisor_profiles", "is_deleted",      FALSE, TRUE) . " = 0
                              AND   " . column_name("crm_supervisor_profiles", "organization_id", FALSE, TRUE) . " = {$params["organization_id"]["id"]}
                              $profile_id_query";

      $profile_query      = $this->db->query($profile_sql_query);
      $supervisor_profile = $profile_query->row_array() ?? [];

      // If profile_id exists than this means its directly selected and we should warn user by its existence (can only happen if cache didnt cleared correctly i guess)
      if(!$supervisor_profile && $params["profile_id"]){
        return rest_response(
          Status_codes::HTTP_NO_CONTENT,
          lang("texts.Curator not found")
        );
      }

    // If no supervisor profile or employee is provided then we are going to create a new one
    // So we need to check if it already exists or not
    } else {
      $fullname                  = str_replace(" ", "", ($params["name"] ?? "") . ($params["surname"] ?? ""));

      $duplicate_check_sql_query = "SELECT
                                          1
                                    FROM `" . main_table_name("crm_supervisor_profiles") . "`
                                    WHERE " . column_name("crm_supervisor_profiles", "is_deleted",      FALSE, TRUE) . " = 0
                                    AND   " . column_name("crm_supervisor_profiles", "organization_id", FALSE, TRUE) . " = {$params["organization_id"]["id"]}
                                    AND   CONCAT(
                                                  TRIM(IFNULL(" . column_name("crm_supervisor_profiles", "name",    FALSE, TRUE) . ", '')),
                                                  TRIM(IFNULL(" . column_name("crm_supervisor_profiles", "surname", FALSE, TRUE) . ", ''))
                                                ) = '$fullname'";

      $duplicate_check_query     = $this->db->query($duplicate_check_sql_query);

      // And if its already exists then there is no need to create a new one or either use existsing one (user will be confused if we dont warn them)
      if($duplicate_check_query->num_rows()){
        return rest_response(
          Status_codes::HTTP_NO_CONTENT,
          lang("texts.Curator already exists")
        );
      }
    }

    // Instead of rewriting it we are gonna update it to create DataHistory (log history) // Wrong choice
    $exist_curator_sql_query = "SELECT
                                      " . column_name("crm_customer_supervisors", "id", TRUE, TRUE) . "
                                FROM `" . main_table_name("crm_customer_supervisors") . "`
                                WHERE " . column_name("crm_customer_supervisors", "is_deleted",      FALSE, TRUE) . " = 0
                                AND   " . column_name("crm_customer_supervisors", "organization_id", FALSE, TRUE) . " = {$params["organization_id"]["id"]}
                                AND   " . column_name("crm_customer_supervisors", "customer_id",     FALSE, TRUE) . " = {$params["customer_id"]}";

    $exist_curator_query     = $this->db->query($exist_curator_sql_query);
    $exist_curator           = $exist_curator_query->row_array();

    Prc::start($this->db);

    // Building base insert list
    $insert_list = [
      column_name("crm_customer_supervisors", "organization_id") => $params["organization_id"]["id"],
      column_name("crm_customer_supervisors", "creator_id")      => $params["operator"]["id"],
      column_name("crm_customer_supervisors", "customer_id")     => $params["customer_id"],
      column_name("crm_customer_supervisors", "is_active")       => STATUS_ACTIVE,
    ];

    // This variable is going to be used to let us know which source we need to use to create supervisor profile
    $base = [];

    if ($params["curator_type"] === "employee") {
      $base = $params["employee"];

      $insert_list = $insert_list + [
        column_name("crm_customer_supervisors", "employee_id")  => $params["employee"]["id"],
      ];
    } else if ($supervisor_profile) {
      $base = $supervisor_profile;

      $insert_list = $insert_list + [
        column_name("crm_customer_supervisors", "supervisor_profile_id")  => $supervisor_profile["id"],
        column_name("crm_customer_supervisors", "employee_id")            => $supervisor_profile["employee_id"],
      ];
    }

    // Using params as source if new customer supervisor is being created
    if (!$base) $base = $params;

    // Adding supervisor profile if it doesn't exists
    if (!$supervisor_profile) {
      $supervisor_profile_insert_list = [
        column_name("crm_supervisor_profiles", "organization_id") => $params["organization_id"]["id"],
        column_name("crm_supervisor_profiles", "employee_id")     => !empty($params["employee"]) ? $params["employee"]["id"] : NULL,
        column_name("crm_supervisor_profiles", "name")            => $base["name"],
        column_name("crm_supervisor_profiles", "surname")         => $base["surname"],
        column_name("crm_supervisor_profiles", "phone")           => $base["phone"],
        column_name("crm_supervisor_profiles", "phone_prefix")    => $base["phone_prefix"],
        column_name("crm_supervisor_profiles", "email")           => $base["email"],
      ];

      $this->db->set(column_name("crm_supervisor_profiles", "process_key"), $params["pid"]);
      $this->db->insert(main_table_name("crm_supervisor_profiles"), $supervisor_profile_insert_list);
      Prc::rollbackOnFailure($this->db);

      $supervisor_profile_id = $this->db->insert_id();

      $insert_list = $insert_list + [
        column_name("crm_customer_supervisors", "employee_id")            => $supervisor_profile_insert_list[column_name("crm_supervisor_profiles", "employee_id")],
        column_name("crm_customer_supervisors", "supervisor_profile_id")  => $supervisor_profile_id,
      ];
    }


    $insert_list = $insert_list + [
      column_name("crm_customer_supervisors", "name")          => $base["name"],
      column_name("crm_customer_supervisors", "surname")       => $base["surname"],
      column_name("crm_customer_supervisors", "phone")         => $base["phone"],
      column_name("crm_customer_supervisors", "phone_prefix")  => $base["phone_prefix"]
      column_name("crm_customer_supervisors", "email")         => $base["email"],
    ];

    DT::softDelete(table: "crm_customer_supervisors", id: $params["customer_id"], column: "customer_id");

    $this->db->set(column_name("crm_customer_supervisors", "process_key"), $params["pid"]);
    $this->db->insert(main_table_name("crm_customer_supervisors"), $insert_list);
    Prc::rollbackOnFailure($this->db);

    Prc::end($this->db);

    return rest_response(
      Status_codes::HTTP_ACCEPTED,
      lang("texts.Curator " . ($exist_curator ? "updated" : "added")),
    );
  }

}
