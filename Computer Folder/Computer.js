function getComputerData(formElement, computer_from_php) {
    var selectElement = document.getElementById('computer_select');
    var finalValue = (selectElement && selectElement.value) ? selectElement.value : computer_from_php;
    var hiddenInput = formElement.querySelector('input[name="computer"]');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'computer';
        formElement.appendChild(hiddenInput);
    }
    hiddenInput.value = finalValue;
}

function renderComputerInfo(computer) {
    const form = document.getElementById('computerForm');
    if (!form) return;

    const comMap = JSON.parse(form.getAttribute('data-computers') || '[]');
    const comData = comMap[computer];

    if (comData) {
        if (form.querySelector('[name="equipment_id"]')) form.querySelector('[name="equipment_id"]').value = comData.Equipment_ID;
        if (form.querySelector('[name="comname"]')) form.querySelector('[name="comname"]').value = comData.ComName;
        if (form.querySelector('[name="ip"]')) form.querySelector('[name="ip"]').value = comData.IP;

        const sectionSelect = form.querySelector('[name="section"]');
        if (sectionSelect) sectionSelect.value = comData.Section || '';
    }
}

document.getElementById('computer_select')?.addEventListener('change', function() {
    if (this.style.display !== 'none') {renderComputerInfo(this.value);}
});

function NewComputer(newUser) {
    const computerSelect = document.getElementById('computerSelect');
    const computerForm = document.getElementById('computerForm');
    const computerButton = document.getElementById('computerButton');
    const computerCreateButton = document.getElementById('computerCreateButton');

    if (newUser) {
        if (computerSelect) computerSelect.style.display = 'none';
        if (computerButton) computerButton.style.display = 'none';
        if (computerCreateButton) computerCreateButton.style.display = 'flex';
        if (typeof resetComputerForm() === "function") resetComputerForm();
    } 
    else {
        if (computerSelect) computerSelect.style.display = 'block';
        if (computerButton) computerButton.style.display = 'flex';
        if (computerCreateButton) computerCreateButton.style.display = 'none';
        if (computerForm) computerForm.reset();
    }
}

function resetComputerForm(){
    if (document.getElementById("equipment_id")) document.getElementById("equipment_id").value = '';
    if (document.getElementById("comname")) document.getElementById("comname").value = '';
    if (document.getElementById("ip")) document.getElementById("ip").value = '';
    if (document.getElementById("section")) document.getElementById("section").value = '';
}

document.getElementById("computerForm")?.addEventListener("submit", function(event) {
    const p= document.getElementById("checkCom");
    p.hidden = true;

    const submitter = event.submitter;

    const equipment_id = document.getElementById("equipment_id").value.trim();
    const comname = document.getElementById("comname").value.trim();
    const ip = document.getElementById("ip").value.trim();
    const section = document.getElementById("section").value.trim();

    if (!equipment_id || !comname || !ip || !section) {
        p.hidden = false;
        event.preventDefault();
        return;
    }

    let confirmMessage = "";
    if (submitter.name === "create_computer") {confirmMessage = "ยืนยันการเพิ่มข้อมูลคอมพิวเตอร์ใหม่ใช่หรือไม่?";} 
    else {confirmMessage = "ยืนยันการแก้ไขข้อมูลคอมพิวเตอร์ใช่หรือไม่?";} 

    if (!confirm(confirmMessage)) {
        event.preventDefault();
    }
});