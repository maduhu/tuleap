


Various Notes concerning CodeX 2.8 to 3.0 upgrade.


Done in 2.8 support branch:
- copy new backup_job in /home/tools
- add sys_default_trove_cat in local.inc
- redirect commit-email.pl to /dev/null


TODO in migration_30
- when moving httpd to httpd_28, don t forget to move the '.subversion' directory back
- Convert BDB to FSFS?


RHEL4 Testing:

/usr/sbin/groupadd -g "104" sourceforge
/usr/sbin/groupadd -g "96" ftpadmin
/usr/sbin/useradd  -c 'Owner of CodeX directories' -M -d '/home/httpd' -p "$1$h67e4niB$xUTI.9DkGdpV.B65r1NVl/" -u 104 -g 104 -s '/bin/bash' -G ftpadmin sourceforge



##############################################
# Database Structure and initvalues upgrade
#
echo "Updating the CodeX database..."

$SERVICE mysql start
sleep 5

pass_opt=""
# See if MySQL root account is password protected
mysqlshow 2>&1 | grep password
while [ $? -eq 0 ]; do
    read -s -p "Existing CodeX DB is password protected. What is the Mysql root password?: " old_passwd
    echo
    mysqlshow --password=$old_passwd 2>&1 | grep password
done
[ "X$old_passwd" != "X" ] && pass_opt="--password=$old_passwd"

echo "Starting DB update for CodeX 3.0. This might take a few minutes."

echo " DB - Fieldset update"
$CAT <<EOF | $MYSQL $pass_opt sourceforge

###############################################################################
# Fieldset: create tables
#
DROP TABLE IF EXISTS artifact_field_set;
CREATE TABLE artifact_field_set (
    field_set_id int(11) unsigned NOT NULL auto_increment,
    group_artifact_id int(11) unsigned NOT NULL default '0',
    name text NOT NULL,
    description text NOT NULL,
    rank int(11) unsigned NOT NULL default '0',
    PRIMARY KEY  (field_set_id),
    KEY idx_fk_group_artifact_id (group_artifact_id)
);

ALTER TABLE artifact_field ADD field_set_id INT( 11 ) UNSIGNED NOT NULL AFTER group_artifact_id;


###############################################################################
# Survey Manager
# 1- create a new table 'survey_radio_choices' to the survey manager database. 
# This table contains all useful information about edited radio buttons, it has 
# 4 columns : 'choice_id', 'question_id', 'radio_choice' and 'choice_rank'
# 2- define a new question type 'Radio Buttons' in 'survey_question_types' table
# 3- change type name of yes/no questions from 'Radio Button Yes/No' to 'Yes/No'
# 
# References:
# request #391
#

## Create the new table 'survey_radio_choices'
CREATE TABLE survey_radio_choices (
  choice_id int(11) NOT NULL auto_increment,
  question_id int(11) NOT NULL default '0',  
  choice_rank int(11) NOT NULL default '0',
  radio_choice text NOT NULL,
  PRIMARY KEY  (choice_id),
  KEY idx_survey_radio_choices_question_id (question_id)  
) TYPE=MyISAM;

## Add new type value 'Radio Buttons', id=6, in 'survey_question_types' table
INSERT INTO survey_question_types (id, type) VALUES (6,'Radio Buttons');

## Replace 'Radio Button Yes/No' value by 'Yes/No' in 'survey_question_types' table
UPDATE survey_question_types SET type='Yes/No' WHERE id='3';

EOF


################################################################################
echo " Upgrading 2.8 if needed"

$PERL <<'EOF'
use DBI;
use Sys::Hostname;
use Carp;

require "/home/httpd/SF/utils/include.pl";  # Include all the predefined functions

&load_local_config();

&db_connect;

# Looking for all trackers
$query_trackers = "SELECT group_artifact_id FROM artifact_group_list";
$result_trackers = $dbh->prepare($query_trackers);
$result_trackers->execute();
# For each tracker ...
while (my ($group_artifact_id) = $result_trackers->fetchrow()) {
    # Create a new fieldset with default name, and attach it to the current tracker
    $insert_fieldset = "INSERT INTO artifact_field_set (group_artifact_id, name, description, rank) VALUES ($group_artifact_id, 'fieldset_default_lbl_key', 'fieldset_default_desc_key', 10)";
    $result_insert_fieldset = $dbh->prepare($insert_fieldset);
    $result_insert_fieldset->execute();

    # Retrieve the id number of the new fieldset just created
    $fieldset_id = $result_insert_fieldset->{'mysql_insertid'};
    
    # Looking for all fields of the current tracker
    $query_fields = "SELECT field_id FROM artifact_field WHERE group_artifact_id=$group_artifact_id";
    $result_fields = $dbh->prepare($query_fields);
    $result_fields->execute();
    # For each field of the current tracker ...
    while (my ($field_id) = $result_fields->fetchrow()) {
        # attach the field to the new fieldset just created
        $update_field = "UPDATE artifact_field SET field_set_id=$fieldset_id WHERE group_artifact_id=$group_artifact_id AND field_id=$field_id";
        $result_update_field = $dbh->prepare($update_field);
        $result_update_field->execute();
    }
}
EOF

echo " DB - Artifact details Field and Follow-up comments update"
$CAT <<EOF | $MYSQL $pass_opt sourceforge

################################################################################
# artifact_history: updating values
#
UPDATE artifact_history 
SET field_name='comment' 
WHERE field_name='details' 

EOF

echo "End of main DB upgrade"
