<?php
/**
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tuleap\OAuth2Server\AuthorizationServer;

use Psr\Http\Message\UriInterface;
use Tuleap\OAuth2Server\App\OAuth2App;

/**
 * @psalm-immutable
 */
final class AuthorizationFormPresenter
{
    /**
     * @var string
     */
    public $app_name;
    /**
     * @var string
     */
    public $project_name;
    /**
     * @var UriInterface
     */
    public $deny_authorization_uri;
    /**
     * @var OAuth2ScopeDefinitionPresenter[]
     */
    public $scope_presenters;

    public function __construct(
        OAuth2App $app,
        UriInterface $deny_authorization_uri,
        OAuth2ScopeDefinitionPresenter ...$scope_presenters
    ) {
        $this->app_name               = $app->getName();
        $this->project_name           = $app->getProject()->getPublicName();
        $this->deny_authorization_uri = $deny_authorization_uri;
        $this->scope_presenters       = $scope_presenters;
    }
}