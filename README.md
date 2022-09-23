This repository is moved to https://lab.civicrm.org/partners/civicoop/bindkracht/org.civicoop.api.caseactivity

org.civicoop.api.caseactivity
=============================

CiviCRM API to retrieve current activities (incl.targets and assignees) for Case

Expected incoming parameter: array with element ['case_id']

Returns: all activities with the case_id that have the is_current_revision field set to 1, sorted by activity_date_time. 
The result includes arrays with targets and assignees (contact_id's and display_names)

