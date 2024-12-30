<?php

namespace App\Controller;

use OpenApi\Attributes\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class InvokableExampleController extends AbstractController
{
    #[Route('/example1', name: 'app_example1', methods: ['GET'])]
    public function __invoke(Request $request)
    {
        $someFlag = (string)$request->query->get('someFlag');

        if ($someFlag == 'value1') {
            throw new BadRequestHttpException();
        }else if($someFlag == 'value2') {
            throw new UnprocessableEntityHttpException();
        }

        throw new \Exception('Regular exception thrown');
    }

}