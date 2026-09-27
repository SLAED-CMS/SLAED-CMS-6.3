<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Correctness of shop, order and forum outside Node: checkout, admin lists, mass actions, CSV import, points compensation and settings texts
final class ShopOrderTest extends TestCase
{
    private static array $files = [];

    # Behaviour runs through tests/Support/shop_probe.php over stubs; the rest is proven off the shipped sources and configuration
    # Run one probe scenario in a fresh process over its own scratch directory and answer its report
    private function getProbe(string $mode, string $name = ''): array
    {
        $script = dirname(__DIR__).'/Support/shop_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_shop_'.$mode.'_'.md5($name).'_'.getmypid();
        $this->deleteTree($work);
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work);
        $out = (string)shell_exec($cmd.' '.escapeshellarg($name).' 2>&1');
        $this->deleteTree($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'Probe '.$mode.' failed');
        return $data['data'];
    }

    # Remove a scratch tree of this test
    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Cut one top-level function out of a source file
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        return substr($code, $from, (int)strpos($code, "\n}\n", $from) - $from);
    }

    # The checkout read the raw cookie shop while the cart is written as <user_c>-shop, so every order met an empty cart
    #[Test]
    public function theCartIsReadFromTheCookieTheSiteWrites(): void
    {
        $data = $this->getProbe('cart');
        $this->assertSame('1,2,2', $data['ids']);
        foreach (['raw', 'empty', 'letter', 'zero', 'trail', 'array', 'broken'] as $key) $this->assertSame('', $data[$key], $key.' passed as a cart');
        $this->assertStringContainsString('getCartCookie()', $this->getBody('modules/shop/index.php', 'kasse'));
        $this->assertStringNotContainsString('setcookie(', $this->getFile('modules/shop/index.php'), 'The shop still writes a raw cookie');
        $this->assertStringNotContainsString("\$_COOKIE['shop']", $this->getFile('core/system.php'), 'The cart still falls back to the raw cookie');
        $this->assertSame(1, substr_count($this->getFile('core/system.php'), "'-shop'] ?? ''"), 'The cart cookie is read in more than one place');
    }

    # The client list passed its count without a table, so the pager counted FROM <prefix> and failed
    #[Test]
    public function thePagerTakesTheCountItIsGiven(): void
    {
        $data = $this->getProbe('pager');
        $this->assertSame(['pages' => 5, 'count' => 45], $data['given']);
        $this->assertSame([], $data['given_sql'], 'A given count still ran a query');
        $this->assertSame(['pages' => 3, 'count' => 23], $data['table']);
        $this->assertSame(['SELECT COUNT(id) FROM sl_products WHERE status=1'], $data['table_sql']);
        $this->assertStringContainsString("'count' => \$numstories", $this->getBody('modules/shop/admin/index.php', 'clients'));
    }

    # The product list joins the categories, which have a status of their own
    #[Test]
    public function theProductListQualifiesItsStatus(): void
    {
        $body = $this->getBody('modules/shop/admin/index.php', 'products');
        $this->assertStringContainsString("_categories AS c ON (p.cid = c.id) WHERE p.'.\$sqlstatus", $body);
    }

    # Each mass action writes the column it names, a category never lands in the comment mode and an unknown mode writes nothing
    public static function getMassActions(): array
    {
        return [
            'category' => ['c7', ['UPDATE sl_products SET cid = :typ WHERE id IN (:id0, :id1)']],
            'moderated comments' => ['m1', ['UPDATE sl_products SET acomm = :typ WHERE id IN (:id0, :id1)']],
            'open comments' => ['m2', ['UPDATE sl_products SET acomm = :typ WHERE id IN (:id0, :id1)']],
            'unknown mode' => ['m5', []],
            'no category' => ['c0', []],
            'bare number' => ['7', []],
        ];
    }

    # One mass action on two products, answered by the statements it ran
    #[Test]
    #[DataProvider('getMassActions')]
    public function theMassActionWritesItsOwnColumn(string $typ, array $sql): void
    {
        $this->assertSame($sql, $this->getProbe('ops', $typ)['sql']);
    }

    # The form offers the categories and the comment modes under prefixes of their own, and no mode that disables comments by mistake
    #[Test]
    public function theMassActionFormKeepsCategoriesApart(): void
    {
        $body = $this->getBody('modules/shop/admin/index.php', 'products');
        $this->assertStringContainsString("'value_attr' => 'c'.\$cid", $body);
        $this->assertStringContainsString("'m1' => _APOSTMOD", $body);
        $this->assertStringContainsString("'m2' => _APOSTNOMOD", $body);
        $this->assertStringNotContainsString("'c0' =>", $body);
    }

    # An import updates or inserts a product without its rating and comment aggregates, and an insert has one value per column
    #[Test]
    public function theImportLeavesTheAggregatesAlone(): void
    {
        $sql = $this->getProbe('import')['sql'];
        $this->assertCount(3, $sql);
        $this->assertStringStartsWith('UPDATE sl_products SET ', $sql[0]);
        foreach (['votes =', 'tvotes =', 'comments ='] as $col) $this->assertStringNotContainsString($col, $sql[0]);
        $this->assertStringContainsString("counter = '12'", $sql[0]);
        foreach ([1 => "VALUES(8, '2'", 2 => "VALUES(NULL, '2'"] as $idx => $head) {
            $this->assertStringContainsString($head, $sql[$idx]);
            $vals = str_getcsv(substr($sql[$idx], (int)strpos($sql[$idx], 'VALUES(') + 7, -1), ',', "'", '\\');
            $this->assertCount(17, $vals, 'An insert does not match the 17 columns of _products');
            $this->assertSame(['0', '12', '0', '0'], array_map('trim', array_slice($vals, 11, 4)), 'comments, counter, votes and tvotes');
        }
    }

    # The import read any posted path under shop/temp, so ../ reached a CSV outside it; only a file the directory lists is read
    #[Test]
    public function theImportReadsOnlyListedFiles(): void
    {
        $this->assertSame([], $this->getProbe('import', 'escape')['sql']);
    }

    # Every procedural owner of a compensation: with the journal lost or the compensation refused it rolls back and answers the error
    public static function getOwners(): array
    {
        $list = [];
        foreach (['shop.clientdel', 'shop.clientset', 'order.delete', 'order.activate', 'forum.delete'] as $owner) {
            foreach (['lost', 'refused', 'kept'] as $case) $list[$owner.' '.$case] = [$owner, $case];
        }
        return $list;
    }

    # One owner in one case: commit and no error only when the compensation was kept
    #[Test]
    #[DataProvider('getOwners')]
    public function aRefusedCompensationRollsItsOwnerBack(string $owner, string $case): void
    {
        $data = $this->getProbe('points', $owner.'|'.$case);
        $kept = $case === 'kept';
        $this->assertSame($kept, in_array('COMMIT', $data['sql'], true), 'commit');
        $this->assertSame(!$kept, in_array('ROLLBACK', $data['sql'], true), 'rollback');
        $this->assertSame($case !== 'lost', in_array('add reverse:9', $data['journal'], true), 'compensation');
        $error = ($data['text'] ?? '') === 'error' && ($data['warn'] ?? false) || ($data['flash'] ?? []) === ['error', true];
        $this->assertSame(!$kept, $error, 'answer');
    }

    # clientsave() has no probe of its own: the stubs of its partner branch would outweigh it, so its source proves the same check
    #[Test]
    public function theClientFormChecksItsCompensation(): void
    {
        $body = $this->getBody('modules/shop/admin/index.php', 'clientsave');
        $this->assertStringContainsString('$done = $rid !== false && (!$rid || $pnt->addEvent(', $body);
        $this->assertStringContainsString('if (!$done) $db->setSqlRollback();', $body);
        $this->assertStringContainsString("\$done ? '' : _ERROR, !\$done", $body);
        foreach (['modules/shop/admin/index.php', 'modules/order/admin/index.php', 'modules/forum/index.php'] as $path) {
            $this->assertStringNotContainsString('if ($rid) $pnt->addEvent(', $this->getFile($path), $path.' drops the answer of a compensation');
        }
    }

    # The Markdown editor removes <br> and joins lone line endings, so the settings texts render in the breaks format
    #[Test]
    public function theSettingsTextsKeepTheirLineBreaks(): void
    {
        $files = ['modules/shop/index.php', 'modules/order/index.php', 'modules/order/admin/index.php', 'modules/money/index.php', 'modules/money/admin/index.php'];
        $num = 0;
        $text = "#\\\$conf\\['(shop|order|money)'\\]\\['(sende|userinfo|partinfo2?|shopinfo|text|info|sendinfo|autor)'\\]#";
        foreach ($files as $path) {
            foreach (array_slice(explode('filterContent(', $this->getFile($path)), 1) as $call) {
                $call = strtok($call, "\n");
                if (!preg_match($text, $call)) continue;
                $this->assertStringContainsString("0, 'breaks')", $call, $path.': '.$call);
                $num++;
            }
        }
        $this->assertSame(15, $num, 'A settings text was added or lost');
    }

    # The contact text uses the same editor field and breaks format as the shop texts, so a save without changes no longer grows an escaped layer
    #[Test]
    public function theContactSettingsSurviveASave(): void
    {
        $form = $this->getBody('modules/contact/admin/index.php', 'contact');
        $this->assertStringContainsString("'name' => 'info', 'value' => (\$conf['contact']['info'] ?? ''),", $form);
        $this->assertStringContainsString("'mod' => 'contact', 'store' => 'config'", $form);
        $this->assertStringNotContainsString("getHtmlFrag('textarea'", $form);
        $this->assertStringContainsString("checkEditorTextRoom(\$cont['info'], 'config')", $this->getBody('modules/contact/admin/index.php', 'save'));
        $this->assertStringContainsString("filterContent(\$conf['contact']['info'], false, \$conf['name'], 0, 'breaks')", $this->getFile('modules/contact/index.php'));
        foreach (['&lt;br&gt;', '&amp;amp;', '&amp;#'] as $mark) {
            $this->assertStringNotContainsString($mark, $this->getFile('config/contact.php'), 'config/contact.php carries '.$mark);
        }
    }

    # The shop texts use the editor field of order and money, the form shows the separator and the currency decoded, and the shipped texts carry no escaped layer
    #[Test]
    public function theShopSettingsSurviveASave(): void
    {
        $form = $this->getBody('modules/shop/admin/index.php', 'config');
        $save = $this->getBody('modules/shop/admin/index.php', 'save');
        foreach (['sende', 'userinfo', 'partinfo', 'partinfo2', 'shopinfo'] as $key) {
            $this->assertStringContainsString("'name' => '".$key."', 'value' => (\$conf['shop']['".$key."'] ?? ''), 'mod' => 'shop', 'store' => 'config'", $form);
        }
        $this->assertStringNotContainsString("getHtmlFrag('textarea'", $form);
        $this->assertStringContainsString("getDecodedText(urldecode(\$conf['shop']['defis'] ?? ''))", $form);
        $this->assertStringContainsString("getDecodedText(\$conf['shop']['valute'])", $form);
        $this->assertStringContainsString("checkEditorTextRoom(\$cont[\$key], 'config')", $save);
        $conf = $this->getFile('config/shop.php');
        foreach (['&lt;br&gt;', '&amp;amp;', '&amp;#'] as $mark) $this->assertStringNotContainsString($mark, $conf, 'config/shop.php carries '.$mark);
    }
}
