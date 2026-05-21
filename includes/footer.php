    </main>
</div>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> Blue Eco Farm Inventory System</p>
</footer>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main    = document.getElementById('mainContent');
    sidebar.classList.toggle('collapsed');
    main.style.marginLeft = sidebar.classList.contains('collapsed') ? '0' : '255px';
}
</script>
</body>
</html>
