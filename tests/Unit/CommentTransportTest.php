<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# A comment action answers the one comment it touched, every mutation is a POST, no token rides in a URL, and only a stored comment clears the form
final class CommentTransportTest extends TestCase
{
    private static array $src = [];

    # Return the source of one function, from its signature to its closing brace at the given indentation
    # These cases read the transport out of the handlers, the form and the shared script; stored rows are in CommentStateTest, the HTTP round in docs/TESTS.md
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
        $beg = strpos($code, 'if ($go == 1 && in_array($op, [');
        $this->assertNotFalse($beg, 'The method guard of the ajax router was not found');
        $this->assertLessThan(strpos($code, 'checkSiteToken($tok)'), $beg, 'The method is not refused before the token');
        $head = substr($code, $beg, 600);
        $this->assertStringContainsString("'addComment', 'updateCommentStatus', 'deleteComment'", $head);
        $this->assertStringContainsString("!== 'POST'", $head);
        $this->assertMatchesRegularExpression('#in_array\(\$op, \[[^]]*\], true\) && \(\$_SERVER\[.REQUEST_METHOD.\] \?\? ..\) !== .POST.#', $head);
    }

    # The editor of a comment is fetched by GET and saved by POST only, each refused in its own handler, and a body is only ever read out of a POST
    #[Test]
    public function theEditorIsFetchedByGetAndSavedByPostOnly(): void
    {
        $open = $this->getSource('core/system.php', 'getQuickEdit');
        $save = $this->getSource('core/system.php', 'updateQuickEdit');
        $this->assertStringContainsString("checkQuickRequest('GET')", $open);
        $this->assertStringContainsString("checkQuickRequest('POST')", $save);
        $this->assertStringContainsString("\$body = getVar('post', 'text', 'raw', '')", $save);
        $this->assertStringNotContainsString("'text'", $open, 'The editor route reads a body');
        $this->assertStringNotContainsString("case 'updateComment':", $this->getFile('index.php'), 'The old edit route is still routed');
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
        $this->assertStringNotContainsString('token=', $this->getFile('templates/lite/fragments/quick-edit.html'), 'The quick editor still builds a token into its url');
    }

    #[Test]
    public function theFormCarriesItsKeyItsTokenAndAPlainAction(): void
    {
        $code = $this->getSource('core/user.php', 'setComShow');
        $this->assertStringContainsString("'input_attr' => 'data-sl-reqkey'", $code);
        $this->assertStringContainsString("'name_attr' => 'token', 'value_attr' => getSiteToken()", $code);
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

    # Every write of the class opens and finishes its transaction through one pair of helpers, kept in one place of the class
    #[Test]
    public function everyWriteGoesThroughOneTransactionPair(): void
    {
        foreach (['addComment', 'updateComment', 'updateBody', 'setStatus', 'deleteComment', 'deleteTarget', 'deleteUser', 'updateCountDrift'] as $name) {
            $one = $this->getSource('core/classes/comment.php', $name, '    ');
            $this->assertStringContainsString('$this->setWriteBegin()', $one, $name.'() writes outside the transaction pair');
            $this->assertStringContainsString('$this->setWriteDone($own)', $one, $name.'() does not finish its transaction through the pair');
        }
    }
}
