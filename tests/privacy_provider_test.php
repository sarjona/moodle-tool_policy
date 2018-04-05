<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy provider tests.
 *
 * @package    tool_policy
 * @category   test
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\collection;
use tool_policy\api;
use tool_policy\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_policy_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Setup function- we will create some policy docs and users.
     */
    public function setUp() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Prepare a policy document with some versions.
        $formdata = api::form_policydoc_data(new \tool_policy\policy_version(0));
        $formdata->name = 'Test policy';
        $formdata->revision = 'v1';
        $formdata->summary_editor = ['text' => 'summary', 'format' => FORMAT_HTML, 'itemid' => 0];
        $formdata->content_editor = ['text' => 'content', 'format' => FORMAT_HTML, 'itemid' => 0];
        $this->policy1 = api::form_policydoc_add($formdata);

        // Create users.
        $this->child = $this->getDataGenerator()->create_user();
        $this->parent = $this->getDataGenerator()->create_user();

        $syscontext = context_system::instance();
        $childcontext = context_user::instance($this->child->id);

        $roleminorid = create_role('Digital minor', 'digiminor', 'Not old enough to accept site policies themselves');
        $roleparentid = create_role('Parent', 'parent', 'Can accept policies on behalf of their child');

        assign_capability('tool/policy:accept', CAP_PROHIBIT, $roleminorid, $syscontext->id);
        assign_capability('tool/policy:acceptbehalf', CAP_ALLOW, $roleparentid, $syscontext->id);

        role_assign($roleminorid, $this->child->id, $syscontext->id);
        role_assign($roleparentid, $this->parent->id, $childcontext->id);
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('tool_policy');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('tool_policy_acceptances', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('policyversionid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('status', $privacyfields);
        $this->assertArrayHasKey('lang', $privacyfields);
        $this->assertArrayHasKey('usermodified', $privacyfields);
        $this->assertArrayHasKey('timecreated', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertArrayHasKey('note', $privacyfields);

        $this->assertEquals('privacy:metadata:acceptances', $table->get_summary());
    }

    /**
     * Test getting the context for the user ID related to this plugin.
     */
    public function test_get_contexts_for_userid() {
        $parentcontext = context_user::instance($this->parent->id);
        $childcontext = context_user::instance($this->child->id);
        api::make_current($this->policy1->get('id'));
        $this->setUser($this->parent);

        // Accept policies for oneself.
        api::accept_policies([$this->policy1->get('id')]);
        $contextlist = provider::get_contexts_for_userid($this->parent->id);
        $this->assertEquals($parentcontext, $contextlist->current());

        // Accept policies also on behalf of somebody else.
        api::accept_policies([$this->policy1->get('id')], $this->child->id);

        $contextlist = provider::get_contexts_for_userid($this->parent->id);
        $this->assertCount(2, $contextlist);
        $this->assertContains($childcontext->id, $contextlist->get_contextids());
        $this->assertContains($parentcontext->id, $contextlist->get_contextids());

        $contextlist = provider::get_contexts_for_userid($this->child->id);
        $this->assertCount(1, $contextlist);
        $this->assertContains($childcontext->id, $contextlist->get_contextids());
        $this->assertNotContains($parentcontext->id, $contextlist->get_contextids());
    }
}
