function showPass(id, eyeid) {
    e = document.getElementById(id);
    e.type = "text";
    eye = document.getElementById(eyeid);
    eye.classList.remove("fa-eye-slash");
    eye.classList.add("fa-eye");
}

function hidePass(id, eyeid) {
    e = document.getElementById(id);
    e.type = "password";
    eye = document.getElementById(eyeid);
    eye.classList.remove("fa-eye");
    eye.classList.add("fa-eye-slash");
}

document.getElementById("passForm")?.addEventListener("submit", function(event) {
    // ดึง Element <p> ทั้งหมดมาเตรียมไว้
    const p1 = document.getElementById("check1");
    const p2 = document.getElementById("check2");
    const p3 = document.getElementById("check3");
    const p4 = document.getElementById("check4");

    // ซ่อน Error เก่าก่อนเริ่มเช็คใหม่
    p1.hidden = p2.hidden = p3.hidden = p4.hidden = true;

    // ดึงค่าจาก Input
    const oldPass = document.getElementById("oldPass").value.trim();
    const newPass = document.getElementById("newPass").value.trim();
    const confirmPass = document.getElementById("confirmPass").value.trim();

    // 1. เช็คค่าว่าง
    if (!oldPass || !newPass || !confirmPass) {
        p1.hidden = false; // แสดง "กรุณากรอกให้ครบ"
        event.preventDefault();
        return;
    }

    // 2. เช็ครหัสผ่านใหม่ตรงกันไหม
    if (newPass !== confirmPass) {
        p3.hidden = false; // แสดง "ยืนยันรหัสผ่านไม่ตรงกัน"
        event.preventDefault();
        return;
    }

    // 3. ถ้าผ่านเงื่อนไขพื้นฐาน ให้ถาม Confirm ก่อน Post ไปหา PHP
    if (!confirm('ยืนยันการเปลี่ยนรหัสผ่านหรือไม่?')) {
        event.preventDefault();
    }
    
    // หมายเหตุ: ส่วนการเช็ค "รหัสผ่านเดิมถูกต้องไหม" (check2) 
    // จะต้องให้ PHP เป็นคนจัดการหลังจากกด Confirm เพราะต้องเทียบกับ Database ครับ
});

function NewUser(newUser) {
    const userSelect = document.getElementById('user_id_select');
    const userInput = document.getElementById('user_id');
    const nameBtn = document.getElementById('nameButton');
    const passBtn = document.getElementById('passButton');
    const sigBtn = document.getElementById('signatureButton');
    const delBtn = document.getElementById('deleteSignatureButton');
    const oldPass = document.getElementById('oldPassRow');
    const nameForm = document.getElementById('nameForm');
    const newUserBtn = document.getElementById('newUserButton');
    const sectionInput = document.getElementById('section');

    if (newUser) {
        // ใช้ if เช็คว่ามี element นั้นจริงไหมก่อนสั่ง style
        if (userSelect) userSelect.style.display = 'none';
        if (userInput) userInput.style.display = 'inline-block';
        
        if (nameBtn) nameBtn.style.display = 'none';
        if (passBtn) passBtn.style.display = 'none';
        if (oldPass) oldPass.style.display = 'none';
        if (newUserBtn) newUserBtn.style.display = 'flex';

        // 1. ล้างค่าแผนกเพื่อให้รายการคอมพิวเตอร์ว่างเปล่าในหน้าสร้างใหม่
        if (sectionInput) sectionInput.value = "";
        // 2. เรียกกรองคอมพิวเตอร์: (แผนกว่าง, ไม่มีคอมเดิม, เป็นหน้าสร้างใหม่ = true)
        updateComputerList("", "", true);

        if (typeof resetNameForm === "function") resetNameForm();
        
        // โหมดสร้างใหม่: บังคับโชว์ Input ว่าง
        renderSignatureUI(null, true);
    } 
    else {
        if (userSelect) userSelect.style.display = 'flex';
        if (userInput) userInput.style.display = 'none';
        
        if (nameBtn) nameBtn.style.display = 'flex';
        if (passBtn) passBtn.style.display = 'flex';
        if (oldPass) oldPass.style.display = 'flex';
        if (newUserBtn) newUserBtn.style.display = 'none';
        
        // หมายเหตุ: sigBtn จะถูกสั่งโชว์/ซ่อนอัตโนมัติข้างใน renderSignatureUI
        if (nameForm) nameForm.reset();

        // โหมดแก้ไข: เช็คค่าจาก select หรือ id ปัจจุบัน
        const currentId = userSelect ? userSelect.value : (userInput ? userInput.value : null);
        if (currentId && typeof renderUserInfo === "function") {renderUserInfo(currentId); } 
        else {updateComputerList("", "", false);}
        
        // เรียกใช้เพื่อให้เช็คว่า User คนนี้มีลายเซ็นเก่าหรือไม่
        renderSignatureUI(currentId);

        document.getElementById('checkUserID').hidden = true;
        document.getElementById('checkName').hidden = true;
        document.getElementById("check1").hidden = true;
    }
}

function resetNameForm(){
    document.getElementById("user_id").value = '';
    document.getElementById("firstname").value = '';
    document.getElementById("lastname").value = '';
    document.getElementById("section").value = '';
    document.getElementById("role").value = '';
    document.getElementById("equipment_id").value = '';
    document.getElementById("comusername").value = '';
    document.getElementById("equipment_id").value = '';
    updateComputerList("", "", true); 
}

function getUserData(formElement, user_id_from_php) {
    // 1. พยายามดึงค่าจาก Select ก่อน (กรณีผู้ใช้เปลี่ยนคนในหน้าเว็บ)
    var selectElement = document.getElementById('user_id_select');
    
    // 2. ถ้าหา Select ไม่เจอ ให้ใช้ค่าที่ส่งมาจาก PHP (user_id_from_php) แทน
    var finalValue = (selectElement && selectElement.value) ? selectElement.value : user_id_from_php;

    // 3. หา input name="user_id" ในฟอร์มนี้
    var hiddenInput = formElement.querySelector('input[name="user_id"]');

    // 4. ถ้าหาไม่เจอ ให้สร้างขึ้นมาใหม่ (Create Element) เพื่อส่งค่าไป PHP
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'user_id';
        formElement.appendChild(hiddenInput);
    }

    // 5. ใส่ค่าสุดท้ายที่เลือกได้
    hiddenInput.value = finalValue;
    console.log("Sending User ID:", finalValue);
}

document.getElementById("signatureForm")?.addEventListener("submit", function(event) {
    const p1 = document.getElementById("checkSignature");
    const p2 = document.getElementById("checkSignatureSize");
    
    // ซ่อน Error เก่าก่อนเริ่มเช็คใหม่
    p1.hidden = p2.hidden = true;

    const signature = document.getElementById("signature");

    const isDeleteAction = event.submitter && event.submitter.name === 'delete_signature';
    if (isDeleteAction){ 
        // ถามยืนยันการลบ
        if (!confirm('ยืนยันการลบลายเซ็นนี้หรือไม่?')) {
            event.preventDefault(); // ยกเลิกการส่งฟอร์มถ้ากด Cancel
        }
        return; // จบการทำงาน (ไม่ต้องไปเช็คไฟล์ข้างล่าง)
    }

    if (signature.files.length === 0) {
        p1.hidden = false; // แสดง "กรุณากรอกให้ครบ"
        event.preventDefault();
        return;
    }
    if (signature.files.length > 0) {
        const fileSize = signature.files[0].size;
        const maxSize = 50 * 1024 * 1024;
        if (fileSize > maxSize) {
            p2.hidden = false;
            event.preventDefault(); // สั่งระงับการส่งฟอร์ม (Stop Form Submission)
            return;
        }
    }

    if (!confirm('ยืนยันการเปลี่ยนลายเซ็นหรือไม่?')) {
        event.preventDefault();
    }
});

// ฟังก์ชันสำหรับดึงข้อมูล Map ลายเซ็นจาก Data Attribute ของ Form
function getSignatureMap() {
    const form = document.getElementById('signatureForm');
    if (!form) return {};
    try {
        return JSON.parse(form.getAttribute('data-signatures') || '{}');
    } 
    catch (e) {
        console.error("เกิดข้อผิดพลาดในการวิเคราะห์ข้อมูลลายเซ็น", e);
        return {};
    }
}

function renderSignatureUI(userId, forceInput = false) {
    const form = document.getElementById('signatureForm');
    const label = document.getElementById('signatureLabel');
    const container = document.getElementById('signatureContainer');
    const sigButton = document.getElementById('signatureButton');
    const delButton = document.getElementById('deleteSignatureButton');
    
    if (!form || !container) return;

    // 1. เช็คโหมด New User (ใช้ radio 'newAccount' จะแม่นยำที่สุด)
    const isNewUserActive = document.getElementById('newAccount')?.checked || false;
    
    const sigMap = JSON.parse(form.getAttribute('data-signatures') || '{}');
    const sigUrl = (forceInput || isNewUserActive) ? null : sigMap[userId];

    if (sigUrl) {
        // --- กรณีมีรูปในระบบ ---
        container.innerHTML = `<img src="${sigUrl}" style="max-height: 80px; display: block; max-width: 225px;">`;
        label.removeAttribute('for'); 
        if (sigButton) sigButton.style.display = 'none'; 
        if (delButton) delButton.style.display = 'flex'; // โชว์ปุ่มลบ
    } else {
        // --- กรณีไม่มีรูป หรือ โหมดสร้างใหม่ ---
        container.innerHTML = '<input type="file" id="signature" name="signature" style="width: 100%; height: auto;" accept="image/*" />';
        label.setAttribute('for', 'signature');
        
        // 2. จัดการปุ่ม (ซ่อนทั้งคู่ถ้าเป็นโหมดสร้างใหม่, ถ้าโหมดแก้ไขให้โชว์ปุ่มอัปโหลด)
        if (sigButton) {
            sigButton.style.display = isNewUserActive ? 'none' : 'flex';
        }
        if (delButton) { 
            delButton.style.display = 'none'; // *** ต้องซ่อนเสมอเพราะไม่มีรูปให้ลบ ***
        }
    }
}

document.getElementById('user_id_select')?.addEventListener('change', function() {
    if (this.style.display !== 'none') {
        renderUserInfo(this.value);      // เปลี่ยนข้อมูลชื่อ/แผนก
        renderSignatureUI(this.value);
    }
});

document.getElementById('newUserForm').addEventListener('submit', function(e) {
    // 1. ปิด Error ทั้งหมดก่อนเริ่มเช็คใหม่
    const errorIds = ['checkUserID', 'checkName', 'check1', 'check3', 'checkSignature', 'checkSignatureSize'];
    errorIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.hidden = true;
    });

    const maxSize = 50 * 1024 * 1024; // 50MB
    let hasError = false;

    // --- ดึงข้อมูลจากจุดต่างๆ ---
    const userId = document.getElementById('user_id').value.trim();
    const firstname = document.querySelector('#nameForm [name="firstname"]').value.trim();
    const lastname = document.querySelector('#nameForm [name="lastname"]').value.trim();
    const section = document.querySelector('#nameForm [name="section"]').value;
    const role = document.querySelector('#nameForm [name="role"]').value;
    const newPass = document.querySelector('#passForm [name="newPass"]').value;
    const confirmPass = document.querySelector('#passForm [name="confirmPass"]').value;
    const sigInput = document.getElementById('signature');

    // --- 2. Validation 6 เงื่อนไข ---
    if (!userId) {
        document.getElementById('checkUserID').hidden = false;
        hasError = true;
    }

    if (!firstname || !lastname || !section || !role) {
        document.getElementById('checkName').hidden = false;
        hasError = true;
    }

    if (!newPass || !confirmPass) {
        document.getElementById('check1').hidden = false;
        hasError = true;
    } else if (newPass !== confirmPass) {
        document.getElementById('check3').hidden = false;
        hasError = true;
    }

    if (sigInput && sigInput.files.length > 0) {
        if (sigInput.files[0].size > maxSize) {
            document.getElementById('checkSignatureSize').hidden = false;
            hasError = true;
        }
    }

    // หยุดการทำงานหากมี Error
    if (hasError) {
        const firstError = document.querySelector('.checkedText:not([hidden])');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        e.preventDefault();
        return;
    }

    // --- 3. ยืนยันการเพิ่มผู้ใช้งาน ---
    if (!confirm('ยืนยันการเพิ่มผู้ใช้งานหรือไม่?')) {
        e.preventDefault();
        return;
    }

    // ---  การย้ายไฟล์ (จุดสำคัญ) ---
    const fileContainer = document.getElementById('fileContainer'); // ที่พักใน newUserForm

    if (sigInput && sigInput.files.length > 0) {
        fileContainer.appendChild(sigInput);
    }

    // --- 4. ถ้าผ่านหมด ให้รวบรวมค่าจากฟอร์มอื่นมาใส่ใน Hidden Input ของฟอร์มนี้ ---
    const targetForm = this;
    const appendHidden = (name, value) => {
        let input = targetForm.querySelector(`input[name="${name}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            targetForm.appendChild(input);
        }
        input.value = value;
    };

    appendHidden('user_id', userId);
    appendHidden('firstname', firstname);
    appendHidden('lastname', lastname);
    appendHidden('section', section);
    appendHidden('role', role);
    appendHidden('newPass', newPass);
    appendHidden('confirmPass', confirmPass);
    appendHidden('equipment_id', document.querySelector('#nameForm [name="equipment_id"]').value || '');
    appendHidden('comusername', document.querySelector('#nameForm [name="comusername"]').value || '');
    appendHidden('create_new_user', '1');

    // หมายเหตุ: ไฟล์จาก sigInput จะถูกส่งไปพร้อมฟอร์มปกติหากคุณใช้การ Submit 
    // โดยย้ายตัว <input type="file"> เข้ามาใน #newUserForm หรือใช้วิธี DataTransfer
});

function renderUserInfo(userId) {
    const form = document.getElementById('nameForm');
    if (!form) return;

    // ดึงข้อมูล User จาก data-users ที่เราเตรียมไว้ใน PHP
    const userMap = JSON.parse(form.getAttribute('data-users') || '{}');
    const userData = userMap[userId];

    if (userData) {
        // หยอดค่าลง Input Text
        if (form.querySelector('[name="firstname"]')) form.querySelector('[name="firstname"]').value = userData.Firstname;
        if (form.querySelector('[name="lastname"]')) form.querySelector('[name="lastname"]').value = userData.Lastname;
        if (form.querySelector('[name="comusername"]')) form.querySelector('[name="comusername"]').value = userData.ComUsername;

        // หยอดค่าลง Select (เช็ค element ก่อนเพราะบาง Role อาจไม่เห็นฟิลด์เหล่านี้)
        const sectionSelect = form.querySelector('[name="section"]');
        const roleSelect = form.querySelector('[name="role"]');
        const equipSelect = form.querySelector('[name="equipment_id"]');

        if (sectionSelect) sectionSelect.value = userData.Section || '';
        if (roleSelect) roleSelect.value = userData.Role || '';
        if (equipSelect) equipSelect.value = userData.Equipment_ID || '';

        // เรียกกรองคอมพิวเตอร์ (โหมดแก้ไข: false)
        updateComputerList(userData.Section, userData.Equipment_ID, false);
    }
}


function updateComputerList(targetSection = "", currentEquipId = "", isNewUser = false) {
    const equipSelect = document.getElementById('equipment_id');
    const allComps = JSON.parse(equipSelect.getAttribute('data-all-comps') || '[]');
    
    // ล้างค่าเก่า
    equipSelect.innerHTML = '<option value="">ไม่มี</option>';

    // ถ้าหน้า "สร้างใหม่" แต่ยังไม่เลือกแผนก -> ไม่ต้องโชว์คอม
    if (isNewUser && !targetSection) return;

    allComps.forEach(comp => {
        const compId = String(comp.Equipment_ID);
        const selectedId = String(currentEquipId);

        // เงื่อนไขแสดงผล: แผนกตรงกัน OR (โหมดแก้ไขและเป็นเครื่องที่ใช้อยู่)
        if (comp.Section === targetSection || (!isNewUser && compId === selectedId)) {
            const opt = document.createElement('option');
            opt.value = comp.Equipment_ID;
            opt.text = comp.Equipment_ID;
            if (compId === selectedId && selectedId !== "") opt.selected = true;
            equipSelect.add(opt);
        }
    });
}

document.getElementById('section')?.addEventListener('change', function() {
    // เช็คว่าตอนนี้เปิดโหมด "สร้างผู้ใช้ใหม่" อยู่หรือไม่
    const isNewUserMode = document.getElementById('user_id').style.display !== 'none';
    updateComputerList(this.value, "", isNewUserMode);
});

/*
https://share.google/aimode/F3FJWqV4XXb3glHou
*/