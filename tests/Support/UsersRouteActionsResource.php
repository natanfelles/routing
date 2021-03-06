<?php
/*
 * This file is part of The Framework Routing Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Routing\Support;

use Framework\Routing\Attributes\Route;
use Framework\Routing\ResourceInterface;
use Framework\Routing\RouteActions;

/**
 * Class UsersRouteActionsResource.
 */
class UsersRouteActionsResource extends RouteActions implements ResourceInterface
{
    #[Route('GET', '/users')]
    public function index() : string
    {
        return __METHOD__;
    }

    #[Route('POST', '/users')]
    public function create() : string
    {
        return __METHOD__;
    }

    #[Route('GET', '/users/{int}')]
    public function show(string $id) : string
    {
        return __METHOD__ . '/' . $id;
    }

    #[Route('PATCH', '/users/{int}')]
    public function update(string $id) : string
    {
        return __METHOD__ . '/' . $id;
    }

    #[Route('PUT', '/users/{int}')]
    public function replace(string $id) : string
    {
        return __METHOD__ . '/' . $id;
    }

    #[Route('DELETE', '/users/{int}')]
    public function delete(string $id) : string
    {
        return __METHOD__ . '/' . $id;
    }
}
