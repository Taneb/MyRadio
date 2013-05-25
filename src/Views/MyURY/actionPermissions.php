<?php

/**
 * Render the Action Permissions management page
 * 
 * @version 24072012
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package URY_Core
 */
for ($i = 0; $i < sizeof($data); $i++) {
  $data[$i]['del'] = array(
      'display' => 'text',
      'url' => CoreUtils::makeURL('Core', 'removeActionPermission', array('permissionid' => $data[$i]['actpermissionid'])),
      'value' => 'Delete'
      );
}
$form->setTemplate('MyURY/actionPermissions.twig')
        ->render(array(
            'tabledata' => $data,
            'tablescript' => 'myury.core.actionPermissions'
        ));