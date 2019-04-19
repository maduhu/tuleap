<?php
/**
 * Copyright Enalean (c) 2018. All rights reserved.
 *
 * Tuleap and Enalean names and logos are registrated trademarks owned by
 * Enalean SAS. All other trademarks or names are properties of their respective
 * owners.
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

namespace Tuleap\Timetracking\REST\v1;

use Luracast\Restler\RestException;
use Project;
use Tracker_FormElementFactory;
use Tracker_REST_TrackerRestBuilder;
use TrackerFactory;
use Tuleap\REST\UserManager as RestUserManager;
use Tuleap\Timetracking\Admin\AdminDao;
use Tuleap\Timetracking\Admin\TimetrackingUgroupDao;
use Tuleap\Timetracking\Admin\TimetrackingUgroupRetriever;
use Tuleap\Timetracking\Permissions\PermissionsRetriever;
use Tuleap\Timetracking\Time\TimeDao;
use Tuleap\Timetracking\Time\TimeRetriever;
use Tuleap\Tracker\REST\PermissionsExporter;
use Tuleap\Tracker\Workflow\PostAction\FrozenFields\FrozenFieldsDao;
use Tuleap\Tracker\Workflow\PostAction\FrozenFields\FrozenFieldDetector;
use Tuleap\Tracker\Workflow\PostAction\FrozenFields\FrozenFieldsRetriever;

class ProjectResource
{
    const TIMETRACKING_CRITERION = 'with_time_tracking';

    /** @var \Tuleap\REST\UserManager */
    private $rest_user_manager;

    /**
     * @var TimeRetriever
     */
    private $time_retriever;

    /**
     * @var TimetrackingOverviewRepresentationsBuilder
     */
    private $timetracking_overview_builder;

    public function __construct()
    {
        $this->rest_user_manager             = RestUserManager::build();
        $this->permissions_retriever         = new PermissionsRetriever(
            new TimetrackingUgroupRetriever(
                new TimetrackingUgroupDao()
            )
        );
        $this->time_retriever                = new TimeRetriever(
            new TimeDao(),
            $this->permissions_retriever,
            new AdminDao(),
            \ProjectManager::instance()
        );
        $this->timetracking_overview_builder = new TimetrackingOverviewRepresentationsBuilder(
            new AdminDao(),
            $this->permissions_retriever,
            TrackerFactory::instance(),
            new Tracker_REST_TrackerRestBuilder(
                Tracker_FormElementFactory::instance(),
                new PermissionsExporter(
                    new FrozenFieldDetector(
                        new FrozenFieldsRetriever(
                            new FrozenFieldsDao()
                        )
                    )
                )
            )
        );
    }

    /**
     * @param  int   $limit
     * @param  int   $offset
     * @param  array $query
     *
     * @return Project[]
     *
     * @throws RestException
     * @throws \Rest_Exception_InvalidTokenException
     * @throws \User_PasswordExpiredException
     * @throws \User_StatusInvalidException
     */
    public function getProjects($limit, $offset, array $query)
    {
        $this->checkQuery($query);
        $current_user = $this->rest_user_manager->getCurrentUser();

        return $this->time_retriever->getProjectsWithTimetracking($current_user, $limit, $offset);
    }

    /**
     * @param array   $query
     * @param String  $representation
     * @param Project $project
     * @param int     $limit
     * @param int     $offset
     *
     * @return array
     *
     * @throws RestException
     * @throws 400
     * @throws \Rest_Exception_InvalidTokenException
     * @throws \User_PasswordExpiredException
     * @throws \User_StatusInvalidException
     */
    public function getTrackers($query, $representation, Project $project, $limit, $offset)
    {
        $this->checkQuery($query);
        $current_user = $this->rest_user_manager->getCurrentUser();
        if ($representation === "minimal") {
            return $this->timetracking_overview_builder->getTrackersMinimalRepresentationsWithTimetracking(
                $current_user,
                $project,
                $limit,
                $offset
            );
        }

        return $this->timetracking_overview_builder->getTrackersFullRepresentationsWithTimetracking(
            $current_user,
            $project,
            $limit,
            $offset
        );
    }

    /**
     * @throws RestException
     */
    private function checkQuery(array $query)
    {
        if ($query[self::TIMETRACKING_CRITERION] === false) {
            throw new RestException(
                400,
                "Searching projects where timetracking is not enabled is not supported. Use 'with_timetracking': true"
            );
        }
    }
}
