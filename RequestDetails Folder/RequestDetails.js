document.addEventListener("DOMContentLoaded", function() {
    const steps = document.querySelectorAll('.progressbar li');
    const colors = {
        done: '#2ecc71',   // เขียว
        active: '#f39c12', // ส้ม
        cancel: '#e74c3c', // แดง
        default: '#ddd'    // เทา
    };

    steps.forEach((li, index) => {
        let myColor = colors.default;
        if (li.classList.contains('done')) myColor = colors.done;
        else if (li.classList.contains('active')) myColor = colors.active;
        else if (li.classList.contains('cancel')) myColor = colors.cancel;

        // 1. ตั้งสีวงกลม (ใช้ตัวแปรเดียวกับใน CSS ของคุณ)
        li.style.setProperty('--circle-color', myColor);

        // 2. ตั้งสี "ครึ่งขวา" ของเส้นที่ลากมาหาตัวเอง (เส้นฝั่งซ้ายของวงกลม)
        // **จุดที่ต้องแก้**: ต้องรับสีมาจากสถานะของตัว "ก่อนหน้า" เพื่อให้เส้นเชื่อมกันสนิท
        const prevLi = steps[index - 1];
        let colorFromPrev = (prevLi && prevLi.classList.contains('done')) ? colors.done : colors.default;
        
        // ถ้าตัวมันเองมีสถานะ (done/active/cancel) ให้เส้นครึ่งที่จ่อเข้าหาตัวมันเป็นสีนั้นๆ 
        // เพื่อให้เส้นสีส้มวิ่งมาจูบวงกลมส้ม หรือเส้นเขียววิ่งมาจูบวงกลมเขียว
        li.style.setProperty('--right-color', myColor);

        // 3. ตั้งสี "ครึ่งซ้าย" ของเส้น "ตัวถัดไป" (เส้นที่พุ่งออกจากตัวมันไปทางขวา)
        const nextLi = steps[index + 1];
        if (nextLi) {
            nextLi.style.setProperty('--left-color', myColor);
        }
    });
});