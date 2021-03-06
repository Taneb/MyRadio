<?php
/**
 * Edit a Banner
 *
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130806
 * @package MyRadio_Website
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Submitted
    $data = MyRadio_Banner::getForm()->readValues();

    if (empty($data['id'])) {
        //create new
        $photo = MyRadio_Photo::create($data['photo']['tmp_name']);
        $banner = MyRadio_Banner::create($photo, $data['alt'], $data['target'], $data['type']);

    } else {
        //submit edit
        $banner = MyRadio_Banner::getInstance($data['id'])
            ->setAlt($data['alt'])
            ->setTarget($data['target'])
            ->setType($data['type']);

        if ($data['photo']['error'] == 0) {
            //Upload replacement Photo
            $banner->setPhoto(MyRadio_Photo::create($data['photo']['tmp_name']));
        }
    }

    CoreUtils::backWithMessage('Banner Updated!');

} else {
    //Not Submitted

    if (isset($_REQUEST['bannerid'])) {
        //edit form
        $banner = MyRadio_Banner::getInstance($_REQUEST['bannerid']);
        $banner
            ->getEditForm()
            ->render(['bannerName' => $banner->getAlt()]);

    } else {
        //create form
        MyRadio_Banner::getForm()->render();
    }
}
