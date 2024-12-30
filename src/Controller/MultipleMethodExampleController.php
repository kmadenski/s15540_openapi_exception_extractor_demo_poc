<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class MultipleMethodExampleController extends AbstractController
{
    #[Route('/example2-1', name: 'app_example2_1', methods: ['PUT'])]
    public function method1()
    {
        throw new \InvalidArgumentException();
    }
    #[Route('/example2-2', name: 'app_example2_2', methods: ['POST'])]
    public function method2(Request $request)
    {
        throw new AccessDeniedHttpException();
    }
    #[Route('/example2-3', name: 'app_example2_3', methods: ['GET'])]
    public function method3(Request $request)
    {
        $someFlag = (bool)$request->query->get('someFlag');

        if ($someFlag) {
            throw new UnprocessableEntityHttpException();
        }

        throw new \Exception('Regular exception thrown');
    }
}