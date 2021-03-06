<?php

/**
 * Gets the full station tracklist - useful for PPL returns.
 *
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130830
 * @package MyRadio_Stats
 */
$start = !empty($_GET['rangesel-starttime']) ? strtotime($_GET['rangesel-starttime']) : time() - (86400 * 28);
$end = !empty($_GET['rangesel-endtime']) ? strtotime($_GET['rangesel-endtime']) : time();

$data = MyRadio_TracklistItem::getTracklistForTime($start, $end);

$format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'html';

switch ($format) {
    case 'csv':
        $file = 'tracklist_'.$start.'-'.$end.'.csv';
        header('Content-Disposition: inline; filename="'.$file.'"');
        header("Content-Transfer-Encoding: Binary");
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        CoreUtils::getTemplateObject()->setTemplate('csv.twig')
            ->addVariable('data', $data)
            ->render();
        break;
    case 'html':
    default:
        $twig = CoreUtils::getTemplateObject()->setTemplate('table_timeinput.twig')
            ->addVariable('title', 'Station Tracklist History')
            ->addVariable('tablescript', 'myury.stats.fulltracklist')
            ->addVariable('starttime', CoreUtils::happyTime($start))
            ->addVariable('endtime', CoreUtils::happyTime($end));

        if (sizeof($data) >= 100000) {
            $twig->addError(
                'You have exceeded the maximum number of results for a '
                .'single query. Please select a smaller timeframe and try again.'
            );
        }

        $twig->addInfo(
            'It\'s probably easier to download this as a <a href="'
            .CoreUtils::makeURL(
                'Stats',
                'fullTracklist',
                [
                    'rangesel-starttime' => $_REQUEST['rangesel-starttime'],
                    'rangesel-endtime' => $_REQUEST['rangesel-endtime'],
                    'format' => 'csv'
                ]
            )
            .'">CSV File</a>.'
        )->addVariable('tabledata', $data)
        ->render();
        break;
}
