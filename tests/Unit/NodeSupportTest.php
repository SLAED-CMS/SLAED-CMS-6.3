<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S14 of docs/node: the comments of a Node material and the private requests of the extension support. The behaviour is driven by
 * tests/Support/route_probe.php with the argument support: the disposable database and scratch configuration of the S13 probe with a fourth
 * type help that carries the extension, real HTTP requests of the owners anna and boris, of helper who is a site account and the operator of
 * help at once, and of administrators without that right, then two child modes that boot the core and ask the comment subsystem and the class
 * NodeSupport directly. The static half reads the files of the stage.
 */
final class NodeSupportTest extends TestCase
{
    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    # Run the probe once in its support mode and memoize the runs; a probe that cannot create its database or start its server is a failure, not a skip
    private function getRuns(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_support';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' support 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            self::$probe = $data;
        }
        return self::$probe['runs']['support'];
    }

    # A request of support is created published with open comments and a queue row waiting for the staff, the subscribed operators are told, and a guest sees nothing
    #[Test]
    public function aNewRequestOpensItsQueueRowAndTellsTheStaff(): void
    {
        $run = $this->getRuns();
        $this->assertSame([403, 403, 403], $run['guest'], 'A guest reaches the list, the form or a request of support');
        [$code, $state, $comon, $card, $mail, $title, $text, $link] = $run['open'];
        $this->assertSame([303, 2, 2], [$code, $state, $comon], 'The request is not stored published with open comments');
        $this->assertSame(['aid' => 0, 'state' => 0, 'prio' => 1, 'version' => 1], $card, 'The queue row is not unassigned, normal and waiting for the staff');
        $this->assertSame(['helper@probe.test', 'root@probe.test'], $mail, 'The new request does not reach exactly the subscribed operators of help');
        $this->assertTrue($title, 'The notice does not name the request');
        $this->assertFalse($text, 'The private text of the request entered the mail queue');
        $this->assertTrue($link, 'The notice does not link the request');
    }

    # Every reader gets his own requests alone: the owner his, the operator all of them, and neither the list nor the page is indexed
    #[Test]
    public function eachReaderSeesOnlyTheRequestsHeMayRead(): void
    {
        $run = $this->getRuns();
        $this->assertSame(['anna' => [true, false], 'boris' => [false, true], 'helper' => [true, true], 'robots' => true, 'chip' => true], $run['lists']);
        [$code, $switch, $form, $robots, $boris, $moder, $helper, $foreign] = $run['view'];
        $this->assertSame([200, true, true, true], [$code, $switch, $form, $robots], 'The owner does not get the page with its switch, the reply form and noindex');
        $this->assertSame([404, 403, 200], [$boris, $moder, $helper], 'Another owner, an administrator without the right or the operator is answered wrongly');
        $this->assertFalse($foreign, 'The operator gets the switch of the owner');
    }

    # A reply of the owner waits for the staff and a reply of the staff for the owner; the counter, the points and the notice of the other side follow the reply
    #[Test]
    public function aReplyMovesTheWaitingSideTheCounterAndTheNotice(): void
    {
        $run = $this->getRuns();
        [$code, $rows, $num, $card, $mail, $text, $pts] = $run['owner'];
        $this->assertSame([303, [1], 1], [$code, $rows, $num], 'The reply of the owner is not stored visible or not counted');
        $this->assertSame(['aid' => 0, 'state' => 0, 'prio' => 1, 'version' => 2], $card, 'The reply of the owner does not wait for the staff with a new version');
        $this->assertSame(['helper@probe.test', 'root@probe.test'], $mail, 'An unassigned request does not tell every subscribed operator');
        $this->assertFalse($text, 'The private reply entered the mail queue');
        $this->assertSame([['comment', 'node.help', 'comment:'.$run['ids'][2]]], $pts, 'The reply is not rewarded under the scope of the type');
        [$code, $rows, $num, $card, $mail, $text] = $run['staff'];
        $this->assertSame([303, [1, 1], 2], [$code, $rows, $num]);
        $this->assertSame(['aid' => 0, 'state' => 1, 'prio' => 1, 'version' => 3], $card, 'The reply of the staff does not wait for the owner');
        $this->assertSame(['anna@probe.test'], $mail, 'The reply of the staff does not reach the owner alone');
        $this->assertFalse($text);
        $this->assertSame([['helper@probe.test'], ['aid' => 5, 'state' => 0, 'prio' => 3, 'version' => 7]], $run['assigned'],
            'A reply of the owner to an assigned request does not reach the assigned operator alone');
    }

    # A private reply stays inside its request: another owner can neither write it, read it in a fragment, nor meet it in a profile feed or a moderation list
    #[Test]
    public function aPrivateReplyReachesNoOtherReader(): void
    {
        $run = $this->getRuns();
        $this->assertSame([303, [1, 1], 2], $run['foreign'], 'Another owner wrote into a foreign request');
        $this->assertSame([true, false, false, false], $run['fragments'], 'A fragment answers the discussion of a foreign request');
        $this->assertSame([false, true, true], $run['profile'], 'The profile feed shows a private reply to a reader of another request, or hides it from its owner');
        $kids = $run['children'];
        $this->assertSame(['admin' => [], 'mods' => [], 'feed' => []], $kids['docsman'], 'An administrator without the right of the type meets its comments');
        $this->assertSame(['help:'.$run['ids'][0]], $kids['helper']['admin'], 'The operator does not meet the comments of his type');
        $this->assertSame(['help'], $kids['helper']['mods']);
        $this->assertSame([], $kids['boris']['feed'], 'The feed of the owner reaches another reader');
        $this->assertSame(['help:'.$run['ids'][0]], $kids['anna']['feed'], 'The owner does not meet his own reply in the feed');
        $this->assertSame([200, true], $run['adminlist'], 'The main administrator does not see the comments of every type');
    }

    # The owner closes and reopens his request with its token and version alone; a closed request is still read but takes no new reply, and every wrong call is refused
    #[Test]
    public function theOwnerClosesAndReopensHisRequest(): void
    {
        $run = $this->getRuns();
        $this->assertSame([303, ['aid' => 0, 'state' => 2, 'prio' => 1, 'version' => 4], true, 0], $run['close'], 'Closing is not stored with its activity or sends a notice');
        $this->assertSame([409, 405, 403, 404, 404], $run['closed'], 'A stale version, GET, a missing token, a foreign owner or a type without support is not refused');
        $this->assertSame([303, [1, 1], false, true, true], $run['locked'], 'A closed request took a reply, offered the form or hid its discussion');
        $this->assertSame(403, $run['author'], 'The owner moved his request to the waiting side of the staff reply');
        $this->assertSame([303, ['aid' => 0, 'state' => 0, 'prio' => 1, 'version' => 5]], $run['reopen']);
        $this->assertSame([403, ['aid' => 0, 'state' => 0, 'prio' => 1, 'version' => 5]], $run['staffswitch'], 'A moderator changed a foreign request through the route of its owner');
    }

    # The working card of the operator assigns, prioritises and keeps the activity, refuses an administrator without the right, a stale version and a missing token
    #[Test]
    public function theWorkingCardChangesTheQueueRow(): void
    {
        $run = $this->getRuns();
        $this->assertSame([200, true, true, true, 404, 404, 404], $run['card'], 'The card does not show the request and its discussion, or opens to the wrong administrator');
        $this->assertSame([303, ['aid' => 5, 'state' => 0, 'prio' => 3, 'version' => 6], true, 0], $run['assign'], 'The assignment is not stored or moved the activity');
        $card = ['aid' => 5, 'state' => 0, 'prio' => 3, 'version' => 6];
        $this->assertSame([422, $card, 409, 403, $card], $run['refuse']);
    }

    # The queue lists the waiting requests by priority and filters them by state and assignment; a bad filter and a foreign moderator are refused,
    # and the list of all types of a moderator whose one type carries the extension reads it through that extension
    #[Test]
    public function theQueueOrdersAndFiltersTheRequests(): void
    {
        $this->assertSame(['both' => [true, true, true], 'closed' => false, 'mine' => [true, false], 'free' => [false, true], 'bad' => 400, 'moder' => 404,
            'single' => 200], $this->getRuns()['queue']);
    }

    # A moderator of the type hides, shows and deletes a reply with the live counter; showing it again is a reply of the staff, and deleting it takes its award back
    #[Test]
    public function moderationKeepsTheCounterOfTheMaterial(): void
    {
        $run = $this->getRuns();
        $this->assertSame([200, 2, 0], $run['hide'], 'Hiding a reply does not lower the counter or moved the queue');
        $this->assertSame([200, 3, 1], $run['show'], 'Showing a reply does not raise the counter or is not answered as a reply of the staff');
        $this->assertSame([200, 2, 1], $run['delete'], 'Deleting a reply does not lower the counter or keeps its award');
    }

    # The class accepts exactly its one switch on a private standard section, refuses every broken map, scopes each reader and knows only the closed actions
    #[Test]
    public function theExtensionChecksItsConfigurationAndScope(): void
    {
        $ext = $this->getRuns()['ext'];
        $this->assertSame(['ok' => true, 'value' => ['mail' => false]], $ext['config']['good']);
        $paths = ['empty' => 'ext.mail', 'number' => 'ext.mail', 'extra' => 'ext.mail', 'rating' => 'features.rating', 'moderation' => 'features.moderation',
            'search' => 'integrations.search', 'guests' => 'workflow.access', 'mode' => 'view.mode'];
        foreach ($paths as $key => $path) $this->assertSame(['ok' => false, 'code' => 3, 'msg' => 'Invalid support input: '.$path], $ext['config'][$key], $key);
        foreach (['swap' => 'support.state', 'order' => 'support.prio', 'lost' => 'support.state', 'text' => 'support.prio'] as $key => $path) {
            $this->assertSame(['ok' => false, 'code' => 3, 'msg' => 'Invalid support input: '.$path], $ext['maps'][$key], 'A broken map was accepted: '.$key);
        }
        $this->assertSame([['join' => '', 'where' => '', 'params' => []], ['join' => '', 'where' => 'n.uid = :owner', 'params' => ['owner' => 2]],
            ['join' => '', 'where' => '1 = 0', 'params' => []]], $ext['scope'], 'The scope of the main administrator, the owner or a guest is wrong');
        foreach (['comment', 'rate', 'favorite', 'asset', 'report'] as $one) $this->assertSame(['ok' => true, 'value' => true], $ext['actions'][$one], $one);
        foreach (['vote', 'Comment'] as $one) $this->assertSame(3, $ext['actions'][$one]['code'], 'An action outside the closed set was accepted: '.$one);
    }

    # The commands refuse a background context, an owner at the queue and bad pages, and a failing hook takes the whole write of the material back
    #[Test]
    public function theCommandsRefuseAndTheHooksRollBack(): void
    {
        $ext = $this->getRuns()['ext'];
        $this->assertSame(3, $ext['data']['code'], 'Extension input of a request was accepted');
        $this->assertSame(['ok' => false, 'code' => 3, 'msg' => 'Invalid support input: transaction'], $ext['notrans'], 'A reply was followed outside a transaction');
        $this->assertSame(2, $ext['taskcard']['code'], 'A background context changed a card');
        $this->assertSame(2, $ext['ownerlist']['code'], 'An owner read the queue of the operators');
        $this->assertSame([3, 3, 3], array_column($ext['badlist'], 'code'), 'A page, limit or state outside the rules reached the queue');
        $this->assertSame([['ok' => false, 'code' => 3, 'msg' => 'Invalid support input: uid'], 0], $ext['noowner'], 'A request without an owner was stored');
        $this->assertSame([['ok' => false, 'code' => 3, 'msg' => 'Invalid support input: comon'], 2], $ext['comon'], 'A request lost its open comments');
    }

    # The stage keeps its files and contracts: the closed factory names the class, the maps stay in config/node.php, the class holds no second list of values,
    # the module adds the one operation, the comment owner locks the material before its row, and the controllers run no SQL of their own
    #[Test]
    public function theStageFilesKeepTheirContract(): void
    {
        $root = self::getRoot();
        $load = (string)file_get_contents($root.'/core/classes/node/ext/load.php');
        $this->assertStringContainsString("\$map = ['support' => ['support.php', 'NodeSupport'], ", $load);
        $code = (string)file_get_contents($root.'/core/classes/node/ext/support.php');
        $this->assertStringContainsString('final class NodeSupport implements NodeExtension', $code);
        $this->assertSame(0, preg_match('/(?:STATE|PRIO)_[A-Z]+/', $code), 'The class declares state or priority constants');
        $this->assertStringNotContainsString("PREFIX_DB.'_comment", $code, 'The extension reaches the comment table');
        foreach (['public function updateNodeSupport(int $id, int $aid, int $state, int $prio, int $version): void',
            'public function getNodeSupportList(NodeType $type, int $page, int $limit, ?int $state = null, ?int $aid = null, ?int $prio = null): array'] as $one) {
            $this->assertStringContainsString($one, $code);
        }
        $this->assertSame(12, preg_match_all('/^    public function /m', $code), 'The class has other public methods than its constructor, the nine of the contract and its two commands');
        $conf = (require $root.'/config/node.php')['node']['support'];
        $this->assertSame(['state' => ['staff' => 0, 'author' => 1, 'closed' => 2], 'prio' => ['low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3]], $conf);
        $node = (string)file_get_contents($root.'/modules/node/index.php');
        $this->assertStringContainsString("return (\$ext === 'support') ? \$ops + ['support' => ['POST']] : \$ops;", $node);
        $this->assertStringNotContainsString('getSqlQuery', $node, 'The public controller runs SQL');
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/modules/node/admin/index.php'), 'The administrative controller runs SQL');
        $com = (string)file_get_contents($root.'/core/classes/comment.php');
        foreach (['setStatus', 'deleteComment'] as $name) {
            $from = strpos($com, 'public function '.$name.'(');
            $body = substr($com, $from, strpos($com, "\n    }\n", $from) - $from);
            $this->assertLessThan(strpos($body, 'FOR UPDATE'), strpos($body, '$this->setNodeLock('), $name.' locks the comment row before the material');
        }
        $from = strpos($com, 'public function addComment(');
        $body = substr($com, $from, strpos($com, "\n    }\n", $from) - $from);
        $this->assertLessThan(strpos($body, 'INSERT INTO'), strpos($body, '$this->setNodeLock('), 'addComment inserts before it locks the material');
        $this->assertStringContainsString('LOCK IN SHARE MODE', $com, 'The live counter is not read with a locking read');
        $this->assertFileExists($root.'/templates/lite/partials/node/support/view.html');
        $this->assertFileExists($root.'/templates/lite/fragments/node/support/card.html');
    }
}
