</div>
<?php
$footerPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$hideFooterOn = ['dashboard.php', 'index.php'];
if (!in_array($footerPage, $hideFooterOn, true)):
?>
    <footer style="margin-top:40px; padding:18px 12px 22px; text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #e5e7eb;">
        &copy; <?php echo date('Y'); ?> Dit is een product van BusAI
    </footer>
<?php endif; ?>

</body>
</html>