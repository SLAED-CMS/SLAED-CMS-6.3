<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 4 of docs/COMMENTS-REDESIGN-2026.md: a comment action answers the one comment it touched instead of
 * repainting the list, every mutation is a POST, no token rides in a URL any more, and the form is only ever
 * cleared by a comment that was actually stored. The response shape is decided by HTMX response headers, so
 * the swap can depend on where the reader is without any of it being baked into cacheable markup.
 * These cases read the transport contract out of the request handlers, the rendered form and the shared
 * script; the stored-row half of the same stage is measured against live rows by CommentStateTest, and the
 * real HTTP round with a signed-in moderator belongs to the browser checks in docs/TESTS.md.
 */
final class CommentTransportTest extends TestCase
{
    private static array $src = [];

    # Return the source of one function, from its signature to its closing brace at the given indentation
    private function getSource(string $file, string $name, string $pad = ''): string
    {
        $key = $file.'::'.$name;
        if (isset(self::$src[$key])) return self::$src[$key];
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
        $beg = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($beg, $name.'() not found in '.$file);
        $end = strpos($code, "\n".$pad."}\n", $beg);
        $this->assertNotFalse($end, $name.'() has no closing brace in '.$file);
        return self::$src[$key] = substr($code, $beg, $end - $beg);
    }

    # Return one repository file as text
    private function getFile(string $file): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
    }

    # The three mutations are POST only, and the router refuses the rest before any handler runs
    #[Test]
    public function mutationsAreRefusedOutsidePost(): void
    {
        $code = $this->getFile('index.php');
        $beg = strpos($code, 'if ($go == 1) {');
        $this->assertNotFalse($beg, 'The ajax router block was not found');
        $head = substr($code, $beg, 600);
        $this->assertStringContainsString("'addComment', 'updateCommentStatus', 'deleteComment'", $head);
        $this->assertStringContainsString("!== 'POST'", $head);
        $this->assertMatchesRegularExpression('#in_array\(\$op, \[[^]]*\], true\) && \(\$_SERVER\[.REQUEST_METHOD.\] \?\? ..\) !== .POST.#', $head);
    }

    # The edit form is the one comment route a GET may still reach, and a save can only read a body out of a POST
    #[Test]
    public function onlyTheEditFormIsReachableWithGet(): void
    {
        $code = $this->getSource('core/system.php', 'updateComment');
        $this->assertStringContainsString("\$text = trim(getVar('post', 'text', 'raw', ''))", $code);
        $this->assertStringNotContainsString("getVar('get', 'text'", $code);
        $this->assertStringContainsString('getTplAjaxTextarea', $code);
    }

    # No comment action carries its token in a URL any more, in the render path or in the shared editor helper
    #[Test]
    public function noCommentActionCarriesATokenInItsUrl(): void
    {
        $view = $this->getSource('core/user.php', 'getCommentView');
        $this->assertStringNotContainsString('token=', $view, 'A comment action still builds a token into its url');
        $this->assertStringNotContainsString('getSiteToken', $view);
        $this->assertStringContainsString("\$act = 'index.php?go=1&op='", $view);
        $this->assertStringContainsString('deleteComment&id=', $view);
        $this->assertSame(3, substr_count($view, "'is_post' => true"), 'A moderation action is still reachable as a plain link');
        $this->assertStringNotContainsString('token=', $this->getSource('core/helpers.php', 'getTplAjaxTextarea'), 'The shared editor still builds a token into its url');
    }

    # Every token in the comment render path is a page token, so a cacheable build stores a signed marker rather than one visitor's token
    #[Test]
    public function everyTokenInTheRenderPathIsAPageToken(): void
    {
        foreach (['getCommentList', 'setComShow', 'addComment'] as $name) {
            $code = $this->getSource('core/user.php', $name);
            $this->assertStringNotContainsString('getSiteToken', $code, $name.'() takes a live token into cacheable markup');
        }
        $this->assertStringContainsString('getPageToken()', $this->getSource('core/user.php', 'getCommentList'));
        $this->assertStringContainsString('getPageToken()', $this->getSource('core/helpers.php', 'getTplAjaxTextarea'));
    }

    # The add answers one comment fragment and never the whole list again
    #[Test]
    public function theAddAnswersOneCommentRatherThanTheList(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringNotContainsString('echo getCommentList(', $code);
        $this->assertStringContainsString('getCommentView(', $code);
        $this->assertStringContainsString("header('HX-Reswap: '.(\$at > 0 ? 'afterend' : (\$total === 1 ? 'innerHTML' : 'afterbegin')))", $code);
        $this->assertStringContainsString("header('HX-Retarget: [id=\\''.\$data['rows'][\$at - 1]['id'].'\\']')", $code);
        $this->assertStringContainsString("header('HX-Retarget: #repcstat')", $code);
    }

    # A published comment that lands outside the reader's slice is announced instead of being pushed into the wrong page
    #[Test]
    public function aCommentOutsideTheSliceIsAnnouncedNotInserted(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringContainsString('_COMMENTS_ADDED', $code);
        $this->assertStringContainsString("if (\$one['id'] === \$row['id'])", $code, 'The response no longer looks the stored comment up in the page it answers');
        $this->assertStringContainsString('if ($at < 0) {', $code, 'A comment the page does not carry is not announced separately');
        $this->assertStringContainsString('_POSTNOTE', $code);
    }

    # Inserting into a full slice also removes the row that falls off the far end, so the page keeps its size
    #[Test]
    public function afullSliceShedsItsFarEndRow(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringContainsString("getHtmlFrag('swap-oob'", $code);
        $this->assertStringContainsString('$drop = intdiv($total - 1, $size)', $code);
        $this->assertStringContainsString('$drop = 2', $code);
        $frag = $this->getFile('templates/lite/fragments/swap-oob.html');
        $this->assertStringContainsString('hx-swap-oob="delete"', $frag);
    }

    # Status and delete name their swap in the response, so a refused action leaves the element the request named alone
    #[Test]
    public function aRefusedActionRemovesNothing(): void
    {
        $view = $this->getSource('core/user.php', 'getCommentView');
        $this->assertSame(3, substr_count($view, "'hx_swap' => 'none'"), 'A moderation action still decides its own swap in the markup');
        $stat = $this->getSource('core/system.php', 'updateCommentStatus');
        $this->assertStringContainsString("header('HX-Reswap: outerHTML')", $stat);
        $this->assertStringContainsString('getCommentView(', $stat);
        $gone = $this->getSource('core/system.php', 'deleteComment');
        $this->assertStringContainsString("header('HX-Reswap: delete')", $gone);
        $this->assertStringContainsString("header('HX-Retarget: #repcstat')", $gone);
    }

    # The form carries a browser-minted idempotency key and a hidden token, and it posts to a real action without HTMX
    #[Test]
    public function theFormCarriesItsKeyItsTokenAndAPlainAction(): void
    {
        $code = $this->getSource('core/user.php', 'setComShow');
        $this->assertStringContainsString("'input_attr' => 'data-sl-reqkey'", $code);
        $this->assertStringContainsString("'name_attr' => 'token', 'value_attr' => getPageToken()", $code);
        $this->assertStringContainsString("'action' => \$post", $code);
        $this->assertStringNotContainsString("'no_action' => true", $code);
        $this->assertStringContainsString("'id' => 'repcstat'", $code);
    }

    # A submit without HTMX stores the comment and answers a 303 back to the target page rather than a bare fragment
    #[Test]
    public function aPlainSubmitAnswersARedirect(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringContainsString("\$live = !empty(\$_SERVER['HTTP_HX_REQUEST'])", $code);
        $this->assertSame(2, substr_count($code, 'if (!$live) setRedirect('), 'The plain path does not answer a redirect on both outcomes');
        $this->assertStringContainsString('303', $code);
    }

    # The key is minted in the browser and the form is only cleared by a stored comment
    #[Test]
    public function theKeyIsMintedInTheBrowserAndClearsOnlyOnSuccess(): void
    {
        $code = $this->getFile('plugins/system/slaed.js');
        $this->assertStringContainsString("document.addEventListener('sl-comment-add'", $code);
        $this->assertStringContainsString('data-sl-reqkey', $code);
        $this->assertStringContainsString('getRandomValues', $code);
        $this->assertStringContainsString('form.reset()', $code);
        foreach (['templates/lite/fragments/button.html', 'templates/admin/fragments/button.html'] as $file) {
            $this->assertStringNotContainsString('hx_on_after', $this->getFile($file), $file.' still resets on every request');
        }
    }

    # The rows have a target of their own, so a fragment can be appended to the list without landing behind the pager
    #[Test]
    public function theRowsHaveATargetOfTheirOwn(): void
    {
        $code = $this->getSource('core/user.php', 'getCommentList');
        $this->assertStringContainsString("'id' => 'repcrows'", $code);
        $rows = strpos($code, "'id' => 'repcrows'");
        $pager = strpos($code, 'getPageNumbers(');
        $this->assertNotFalse($pager, 'The list no longer renders a numbered pager');
        $this->assertLessThan($pager, $rows, 'The pager is rendered inside the row container');
        $this->assertStringContainsString("'hx_target' => '#repcrows'", $this->getSource('core/user.php', 'setComShow'));
    }

    # The next page is appended by a control that replaces itself, and the same control is an ordinary page link without HTMX
    #[Test]
    public function theNextPageIsAppendedByAControlThatReplacesItself(): void
    {
        $code = $this->getSource('core/user.php', 'getCommentRows');
        $this->assertStringContainsString("'hx_target' => 'this'", $code);
        $this->assertStringContainsString("'hx_swap' => 'outerHTML'", $code);
        $this->assertStringContainsString("'hx_url' => 'index.php?go=1&op=getCommentPage", $code);
        $this->assertStringContainsString("'hx_headers' => \$token", $code);
        $this->assertStringContainsString("getSeoUrl(['name' => \$mod, \$pag.'&com' => \$next])", $code, 'The control is not an ordinary page link without HTMX');
        $this->assertStringContainsString("if (\$data['page'] >= \$data['pages']) return \$cont", $code, 'The last page still offers to load a page after it');
        $this->assertStringContainsString("case 'getCommentPage': getCommentPage(); break;", $this->getFile('index.php'));
        $route = $this->getSource('core/user.php', 'getCommentPage');
        $this->assertStringContainsString("if (\$data['total'] < 1 || \$data['page'] !== \$page) return;", $route, 'A page past the last is clamped and answered twice');
        $this->assertStringNotContainsString('getPageNumbers(', $route, 'The appended slice carries a second pager');
    }

    # A link that names one comment lands on the page that shows it, instead of an anchor into a page that does not carry it
    #[Test]
    public function aCommentLinkNamesThePageThatShowsIt(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringContainsString("'&op=view&id='.\$id.'&at='.\$new['id'].'#'.\$new['id']", $code, 'The notification still links a bare anchor');
        $this->assertStringContainsString("\$seen = \$back.'&at='.\$row['id'].'#'.\$row['id']", $code, 'The other-page notice still links a bare anchor');
        $show = $this->getSource('core/user.php', 'setComShow');
        $this->assertStringContainsString("\$com->getRootPage(\$full ?: getVar('get', 'at', 'num', 0))", $show, 'The page of a named comment is not resolved');
        $root = $this->getSource('core/classes/comment.php', 'getRootPage', '    ');
        $this->assertStringContainsString('WITH RECURSIVE up AS (', $root, 'The root of a comment is not resolved by walking its parent chain');
        $this->assertStringContainsString('WHERE k.pid = 0', $root, 'The rank is not counted over the roots the page renders');
    }

    # The rest of a capped branch is appended by its own control, which also stays an ordinary link that expands the branch on the server
    #[Test]
    public function theRestOfABranchIsAppendedByItsOwnControl(): void
    {
        $code = $this->getSource('core/user.php', 'getCommentRows');
        $this->assertSame(2, substr_count($code, "'hx_target' => 'this'"), 'A control does not replace itself with its own answer');
        $this->assertStringContainsString('op=getCommentBranch', $code);
        $this->assertStringContainsString("getSeoUrl(['name' => \$mod, \$pag.'&all' => \$val['id']])", $code, 'The reply control has no plain link');
        $this->assertStringContainsString("intval(\$val['kids'] ?? 0) > intval(\$val['shown'] ?? 0)", $code, 'The control shows even when the branch is complete');
        $branch = $this->getSource('core/user.php', 'getCommentBranch');
        $this->assertStringContainsString('$com->getBranch($id, $reps, $skip)', $branch);
        $this->assertStringContainsString("\$data['left'] > 0", $branch, 'The answer offers a further control even when nothing is left');
        $this->assertStringContainsString("case 'getCommentBranch': getCommentBranch(); break;", $this->getFile('index.php'));
        $show = $this->getSource('core/user.php', 'setComShow');
        $this->assertStringContainsString("getVar('get', 'all', 'num', 0)", $show, 'The plain reply link is not resolved on the server');
        $this->assertStringContainsString('$full', $this->getSource('core/classes/comment.php', 'getList', '    '), 'The class cannot answer one branch whole');
    }

    # The number of replies a page shows under one comment is a setting, read by the class and written by the moderation form
    #[Test]
    public function theReplyCapIsASetting(): void
    {
        $this->assertStringContainsString("'reps' => '5'", $this->getFile('config/comments.php'));
        $admin = $this->getFile('admin/modules/comments.php');
        $this->assertStringContainsString("'name_attr' => 'reps'", $admin, 'The setting has no field in the moderation preferences');
        $this->assertStringContainsString("'reps' => getVar('post', 'reps', 'num', 5)", $admin, 'The setting is rendered but never saved');
        $this->assertStringContainsString('_COMMENTS_REPS', $admin);
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $one) {
            $this->assertStringContainsString("define('_COMMENTS_REPS'", $this->getFile('lang/'.$one.'.php'), $one.' is missing the label');
            $this->assertStringContainsString("define('_COMMENTS_REPLIES'", $this->getFile('lang/'.$one.'.php'), $one.' is missing the control label');
        }
    }

    # The page cache is invalidated by the class after a successful write, not by the route that happened to be called
    #[Test]
    public function theClassInvalidatesTheCacheAndTheRouteNoLongerDoes(): void
    {
        $code = $this->getFile('index.php');
        $this->assertStringContainsString("in_array(\$op, ['updatePost', 'updateVotingResult'], true)) Cache::addEpoch()", $code);
        $this->assertDoesNotMatchRegularExpression("#in_array\(\\\$op, \[[^]]*Comment[^]]*\], true\)\) Cache::addEpoch\(\)#", $code);
        $class = $this->getFile('core/classes/comment.php');
        $this->assertSame(7, substr_count($class, 'Cache::addEpoch();'), 'The seven writes of the class do not all invalidate');
        foreach (['addComment', 'updateComment', 'setStatus', 'deleteComment', 'deleteTarget', 'deleteUser', 'updateCountDrift'] as $name) {
            $this->assertStringContainsString('Cache::addEpoch();', $this->getSource('core/classes/comment.php', $name, '    '), $name.'() stores without invalidating');
        }
    }
}
