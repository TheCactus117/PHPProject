<?php

require __DIR__ . DIRECTORY_SEPARATOR . '../vendor/autoload.php';

use Model\InMemoryFinder;
use Model\JsonDAO;
use Model\Connection;
use Model\StatusQuery;
use Model\Status;
use Model\StatusDataMapper;
use Http\Request;
use Http\Response;
use Exception\HttpException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

// Config
$debug = true;

$app = new \App(new View\TemplateEngine(
    __DIR__ . '/templates/'
), $debug);

$jsonFile = __DIR__ .  DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'statuses.json';

$encoders = array(new XmlEncoder(), new JsonEncoder());
$normalizers = array(new GetSetMethodNormalizer());
$serializer = new Serializer($normalizers, $encoders);

// $memoryFinder = new InMemoryFinder();
// $memoryFinder = new JsonDAO($jsonFile);

$connection = new Connection("mysql", "uframework", "localhost", "uframework", "passw0rd");
$statusQuery = new StatusQuery($connection);                                                // Rename in DatabaseFinder
$statusDataMapper = new StatusDataMapper($connection);

/**
 * Index
 */
$app->get('/', function () use ($app) {
    return $app->render('index.php');
});

$app->get('/index', function () use ($app) {
    return $app->render('index.php');
});

$app->get('/statuses', function (Request $request) use ($app, $statusQuery, $serializer) {
    $statuses = $statusQuery->findAll();

    $format = $request->guessBestFormat();
    if ('json' !== $format && 'xml' !== $format) {
        return $app->render('statuses.php', array('array' => $statuses));
    }
    $response = null;
    if ('json' === $format) {
        $response = new Response($serializer->serialize($statuses, $format), 200, array('Content-Type' => 'application/json'));
    }
    if ('xml' === $format) {
        $response = new Response($serializer->serialize($statuses, $format), 200, array('Content-Type' => 'application/xml'));
    }

    $response->send();
});

$app->get('/statuses/(\d+)', function (Request $request, $id) use ($app, $statusQuery, $serializer) {
    $status = $statusQuery->findOneById($id);
    if (null === $status) {
        throw new HttpException(404, "Object doesn't exist");
    }

    $format = $request->guessBestFormat();
    if ('json' !== $format && 'xml' !== $format) {
        return $app->render('status.php', array('item' => $status));
    }
    $response = null;
    if ('json' === $format) {
        $response = new Response($serializer->serialize($status, $format), 200, array('Content-Type' => 'application/json'));
    }
    if ('xml' === $format) {
        $response = new Response($serializer->serialize($status, $format), 200, array('Content-Type' => 'application/xml'));
    }

    $response->send();
});

$app->post('/statuses', function (Request $request) use ($app, $statusDataMapper) {
    $author = $request->getParameter('username');
    $content = $request->getParameter('message');
    $status = new Status($content, null, $author, new DateTime());
    $return = $statusDataMapper->persist($status);
    if (null === $return) {
        throw new HttpException(400, 'Status content too large (140 characters maximum).');
    }

    $format = $request->guessBestFormat();
    if ('json' !== $format) {
        $app->redirect('/statuses');
    }
    $response = null;
    if ('json' === $format) {
        $response = new Response(json_encode($status), 201, array('Content-Type' => 'application/json'));
    }

    $response->send();
});

$app->delete('/statuses/(\d+)', function (Request $request, $id) use ($app, $statusQuery, $statusDataMapper) {
    $status = $statusQuery->findOneById($id);
    if (null === $status) {
        throw new HttpException(404, "Object doesn't exist");
    }
    $statusDataMapper->remove($status);

    $format = $request->guessBestFormat();
    if ('json' !== $format) {
        $app->redirect('/statuses');
    }
    $response = null;
    if ('json' === $format) {
        $response = new Response("{\"status\": \"Status suppressed\"", 204, array('Content-Type' => 'application/json'));
    }

    $response->send();
});

return $app;
