document.addEventListener('DOMContentLoaded', function () {
    const menuItems = document.querySelectorAll('.sidebar-menu a');
    const sections = document.querySelectorAll('.section');

    menuItems.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');

            // Xóa class active khỏi tất cả menu items và sections
            menuItems.forEach(i => i.parentElement.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            // Thêm class active cho menu item và section được chọn
            this.parentElement.classList.add('active');
            document.getElementById(sectionId).classList.add('active');
        });
    });
    document.getElementById('create-note').addEventListener('click', function () {
        document.getElementById('note-modal').classList.add('active');
    });
    
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.modal').classList.remove('active');
        });
    });
});