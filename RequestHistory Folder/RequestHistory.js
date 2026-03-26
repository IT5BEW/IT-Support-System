function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("historyTable");
    tr = table.getElementsByTagName("tr");

    // วนลูปดูทุกแถว (เริ่มที่ 1 เพราะ 0 คือหัวตาราง)
    for (i = 1; i < tr.length; i++) {
        tr[i].style.display = "none"; // ซ่อนแถวไว้ก่อน
        td = tr[i].getElementsByTagName("td");
        
        // วนลูปดูทุกคอลัมน์ในแถวนั้นๆ
        for (j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = ""; // ถ้าเจอคำที่ค้นหา ให้แสดงแถวนั้น
                    break; // เมื่อเจอแล้วให้หยุดลูปในคอลัมน์แล้วไปแถวถัดไป
                }
            }
        }
    }
}

function filterStatus(status) {
    var table = document.getElementById("historyTable");
    var tr = table.getElementsByTagName("tr");

    for (var i = 1; i < tr.length; i++) {
        // คอลัมน์สถานะคือ td ตัวที่ 4 (index 3)
        var td = tr[i].getElementsByTagName("td")[3]; 
        if (td) {
            var txtValue = td.textContent || td.innerText;
            
            if (status === 'all') {
                tr[i].style.display = ""; // แสดงทั้งหมด
            } else if (txtValue.indexOf(status) > -1) {
                tr[i].style.display = ""; // แสดงเฉพาะที่ตรงกับคำที่ส่งมา
            } else {
                tr[i].style.display = "none"; // ซ่อนแถวที่ไม่เกี่ยว
            }
        }
    }
}