<?php

/**
 * CaseActivity.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_case_activity_get_spec(&$spec) {
  $spec['case_id']['api.required'] = 1;
}

/**
 * CaseActivity.Get API
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 13 Feb 2014
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_case_activity_get($params) {
    if (array_key_exists('case_id', $params) && !empty($params['case_id']) 
        && is_numeric($params['case_id'])) {
        $caseActivities = array();
        /*
         * retrieve all activities for case
         */
        $query = 
"SELECT a.activity_id, b.source_contact_id, c.display_name AS source_name, 
b.activity_type_id, d.label as activity_type, b.subject, b.activity_date_time, 
b.location, b.status_id, e.label AS status, b.priority_id, f.label AS priority, 
b.medium_id, g.label AS medium, b.details
FROM dgw_mutcivi.civicrm_case_activity a
LEFT JOIN civicrm_activity b ON a.activity_id = b.id
LEFT JOIN civicrm_contact c ON b.source_contact_id = c.id
LEFT JOIN civicrm_option_value d ON b.activity_type_id = d.value AND d.option_group_id = 2
LEFT JOIN civicrm_option_value e ON b.status_id = e.value AND e.option_group_id = 25
LEFT JOIN civicrm_option_value f ON b.priority_id = f.value AND f.option_group_id = 38
LEFT JOIN civicrm_option_value g ON b.medium_id = g.value AND g.option_group_id = 57
WHERE a.case_id = {$params['case_id']} AND b.is_current_revision = 1";
        $dao = CRM_Core_DAO::executeQuery($query);
        while ($dao->fetch()) {
            $fields = get_object_vars($dao);
            $caseActivity = array();
            foreach ($fields as $fieldKey => $fieldValue) {
                if (substr($fieldKey, 0, 1) != "_" && $fieldKey != "N") {
                    $caseActivity[$fieldKey] = $fieldValue;
                }
            }
            /*
             * get targets
             */
            $targets = array();
            $activityTargets = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($dao->activity_id);
            foreach($activityTargets as $activityTarget) {
                $target = array();
                $target['target_contact_id'] = $activityTarget;
                $apiParams = array(
                    'id'        =>  $activityTarget,
                    'return'    =>  'display_name'
                );
                $target['target_contact_name'] = civicrm_api3('Contact', 'Getvalue', $apiParams);
                $targets[] = $target;
            }
            $caseActivity['targets'] = $targets;
            /*
             * get assignees
             */
            $assignees = array();
            $activityAssignees = CRM_Activity_BAO_ActivityAssignment::retrieveAssigneeIdsByActivityId($dao->activity_id);
            foreach($activityAssignees as $activityAssignee) {
                $assignee = array();
                $assignee['assignee_contact_id'] = $activityAssignee;
                $apiParams = array(
                    'id'        =>  $activityAssignee,
                    'return'    =>  'display_name'
                );
                $assignee['assignee_contact_name'] = civicrm_api3('Contact', 'Getvalue', $apiParams);
                $assignees[] = $assignee;
            }
            $caseActivity['assignees'] = $assignees;
            $caseActivities[] = $caseActivity;
        }
        return civicrm_api3_create_success($caseActivities, $params, 'NewEntity', 'NewAction');
    } else {
        throw new API_Exception('Params has to contain case_id. Case_id can not 
            be empty and has to be numeric');
    }
}