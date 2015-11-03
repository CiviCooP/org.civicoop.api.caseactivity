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
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_case_activity_get($params) {
  if (array_key_exists('case_id', $params) && !empty($params['case_id']) && is_numeric($params['case_id'])) {
    $caseActivities = array();
    /*
     * retrieve all activities for case
     */
    $select = "SELECT a.activity_id, h.contact_id, c.display_name AS source_name, b.activity_type_id,
      d.label as activity_type, b.subject, b.activity_date_time, b.location, b.status_id, e.label AS status,
      b.priority_id, f.label AS priority, b.medium_id, g.label AS medium, b.details
      FROM civicrm_case_activity a
      LEFT JOIN civicrm_activity b ON a.activity_id = b.id
      LEFT JOIN civicrm_activity_contact h ON a.activity_id = h.activity_id AND h.record_type_id = %1
      LEFT JOIN civicrm_contact c ON h.contact_id = c.id
      LEFT JOIN civicrm_option_value d ON b.activity_type_id = d.value AND d.option_group_id = %2
      LEFT JOIN civicrm_option_value e ON b.status_id = e.value AND e.option_group_id = %3
      LEFT JOIN civicrm_option_value f ON b.priority_id = f.value AND f.option_group_id = %4
      LEFT JOIN civicrm_option_value g ON b.medium_id = g.value AND g.option_group_id = %5";
    $queryParams = array(
      1 => array(2, 'Integer'),
      2 => array(_getOptionGroupId('activity_type'), 'Integer'),
      3 => array(_getOptionGroupId('activity_status'), 'Integer'),
      4 => array(_getOptionGroupId('priority'), 'Integer'),
      5 => array(_getOptionGroupId('encounter_medium'), 'Integer'),
      6 => array($params['case_id'], 'Integer'),
      7 => array(1, 'Integer'),
      8 => array(0, 'Integer')
    );
    $where = "WHERE a.case_id = %6 AND b.is_current_revision = %7 AND b.is_deleted = %8";
    if (isset($params['activity_type_id'])) {
      $where .= ' AND b.activity_type_id = %9';
      $queryParams[9] = array($params['activity_type_id'], 'Integer');
    }
    $orderBy = "ORDER BY b.activity_date_time DESC";
    $query = $select.' '.$where.' '.$orderBy;

    $dao = CRM_Core_DAO::executeQuery($query, $queryParams);
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
      _getActivityCustomData($caseActivity);
      $caseActivities[$caseActivity['activity_id']] = $caseActivity;
    }
    return civicrm_api3_create_success($caseActivities, $params, 'CaseActivity', 'Get');
  } else {
    throw new API_Exception('Params has to contain case_id. Case_id can not
      be empty and has to be numeric');
  }
}
/**
 * Function to get custom groups, fields and data if there is any
 *
 * @param array $caseActivity
 */
function _getActivityCustomData(&$caseActivity) {
  $customGroupParams = array(
    'extends' => 'Activity',
    'extends_entity_column_value' => $caseActivity['activity_type_id'],
    'is_active' => 1);
  try {
    $customGroups = civicrm_api3('CustomGroup', 'Get', $customGroupParams);
    foreach ($customGroups['values'] as $customGroupId => $customGroup) {
      $customFieldParams = array(
        'custom_group_id' => $customGroupId,
        'is_active' => 1);
      try {
        $customFields = civicrm_api3('CustomField', 'Get', $customFieldParams);
        _selectActivityCustomData($customGroup['table_name'], $customFields, $caseActivity);
      } catch (CiviCRM_API3_Exception $ex) {
      }
    }
  } catch (CiviCRM_API3_Exception $ex) {
  }
}
function _selectActivityCustomData($tableName, $customFields, &$caseActivity) {
  $selectFields = array();
  foreach ($customFields['values'] as $customFieldId => $customRecord) {
    $selectFields[] = $customRecord['column_name'];
  }
  $query = 'SELECT '.implode(', ', $selectFields).' FROM '.$tableName.' WHERE entity_id = %1';
  $queryParams = array(1 => array($caseActivity['activity_id'], 'Integer'));
  $daoCustomData = CRM_Core_DAO::executeQuery($query, $queryParams);
  if ($daoCustomData->fetch()) {
    foreach ($selectFields as $fieldColumnName) {
      $caseActivity[$fieldColumnName] = $daoCustomData->$fieldColumnName;
    }
  }
}
function _getOptionGroupId($name) {
  $params = array(
    'name' => $name,
    'return' => 'id');
  return civicrm_api3('OptionGroup', 'Getvalue', $params);
}

