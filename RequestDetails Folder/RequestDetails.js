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
            if (!li.classList.contains('cancel')) nextLi.style.setProperty('--left-color', myColor);
        }
    });
});

var pdfFile

const { PDFDocument } = PDFLib
const { TextAlignment } = PDFLib

async function CreateForm(data, fileName) {
    // Get Thai Font
    const thaiFontUrl = '../- Font/THSarabunNew.ttf'
    const thaiFontBytes = await fetch(thaiFontUrl).then(res => res.arrayBuffer())

    // Fetch the PDF with form fields
    const formUrl = '../- PDF/IT Request Form.pdf'
    const formPdfBytes = await fetch(formUrl).then(res => res.arrayBuffer())

    // Load a PDF with form fields
    const pdfDoc = await PDFDocument.load(formPdfBytes)

    pdfDoc.registerFontkit(fontkit)
    const thaiFont = await pdfDoc.embedFont(thaiFontBytes)

    // Get the form containing all the fields
    const form = pdfDoc.getForm()

    // ฟังก์ชันช่วยแปลง Base64 เป็น Uint8Array ---
    const base64ToUint8Array = (base64) => {
        const base64String = base64.includes(',') ? base64.split(',')[1] : base64;
        try {
            const binaryString = window.atob(base64String);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return bytes;
        } 
        catch (e) {
            console.error("Base64 decoding failed:", e);
            return null;
        }
    };

    // ฟังก์ชันใส่รูปลงในฟิลด์ลายเซ็น
    const handleSignature = async (signatureBase64, mimeType, targetField) => {
        if (!signatureBase64 || !targetField) return;

        try {
            const sigBytes = base64ToUint8Array(signatureBase64);
            if (!sigBytes) throw new Error("Invalid byte array");

            let embeddedSig;
            const type = mimeType?.toLowerCase();

            // ตรวจสอบ Mime Type และ Embed รูปภาพ
            if (type === 'image/png') {embeddedSig = await pdfDoc.embedPng(sigBytes);} 
            else if (type === 'image/jpeg' || type === 'image/jpg') {embeddedSig = await pdfDoc.embedJpg(sigBytes);} 
            else {embeddedSig = await pdfDoc.embedPng(sigBytes);}

            // ใส่รูปลงในปุ่ม (Button Field)
            targetField.setImage(embeddedSig);

        } 
        catch (error) {
            console.error("Signature Error:", error);
            // กรณี Error ให้เอามือไปใส่ชื่อ Text แทน (Fallback)
            if (fields.userName) {
                fields.userName.setText(data.User || "");
            }
        }
    };

    // ฟังก์ชันจัดการวันที่
    const handleDate = (dateStr, dField, mField, yField) => {
        const date = new Date(dateStr);
        const [d, m, y] = date.toLocaleDateString('th-TH', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        }).split(' ');

        dField.setText(d);
        mField.setText(m);
        yField.setText(y);
    };

    // Get all fields in the PDF by their names
    const fields = {
        chkFixCom: form.getCheckBox('Check Box1'),
        chkFixEtc: form.getCheckBox('Check Box2'),
        equipmentId: form.getTextField('Equipment ID'),
        comName: form.getTextField('Com Name'),
        user: form.getTextField('User'),
        section: form.getTextField('Section'),
        chkReinstall: form.getCheckBox('Check Box3'),
        chkBroken: form.getCheckBox('Check Box4'),
        chkEtcDetail: form.getCheckBox('Check Box5'),
        etcText: form.getTextField('fill_2'),
        cause1: form.getTextField('Text6'),
        cause2: form.getTextField('Text7'),
        cause3: form.getTextField('Text8'),
        userName: form.getTextField('Text9'),
        dayCreate: form.getTextField('Text10'),
        monthCreate: form.getTextField('Text11'),
        yearCreate: form.getTextField('Text12'),
        approverName: form.getTextField('fill_3'),
        dayApprove: form.getTextField('Text13'),
        monthApprove: form.getTextField('Text14'),
        yearApprove: form.getTextField('Text15'),
    };

    const userSignatureField = form.getButton('Image1');
    const approverSignatureField = form.getButton('Image2');

    // Set Font / Size / Alignment for all fields
    Object.values(fields).forEach(field => {
        if (field && typeof field.setAlignment === 'function') {
            field.setFontSize(16);
            field.setAlignment(TextAlignment.Center);
            field.updateAppearances(thaiFont);
        }
    });

    fields.etcText.setAlignment(TextAlignment.Left);
    fields.cause1.setAlignment(TextAlignment.Left);
    fields.cause2.setAlignment(TextAlignment.Left);
    fields.cause3.setAlignment(TextAlignment.Left);


// User Report Section
    // Fill in the basic info fields
    if (data.FixCom) fields.chkFixCom.check();
    if (data.FixETC) fields.chkFixEtc.check();
    
    fields.equipmentId.setText(data.EquipmentID);
    fields.comName.setText(data.ComName);
    fields.user.setText(data.User);
    fields.section.setText(data.Section);

    if(data.ReInstall) fields.chkReinstall.check();
    if(data.Broken) fields.chkBroken.check();
    if(data.ETC) {
        fields.chkEtcDetail.check();
        fields.etcText.setText(data.ETCText);
    } else {
        fields.etcText.setText('');
    }

    // Fill in Cause Text Fields
    fields.cause1.setText(data.Cause1);
    fields.cause2.setText(data.Cause2);
    fields.cause3.setText(data.Cause3);

    // Fill in Name Field
    if (data.UseSignature && data.Signature && data.Signature !== "") {
        handleSignature(data.Signature, data.SignatureMime, userSignatureField);
        // try {
        //     const sigBytes = base64ToUint8Array(data.Signature);
        //     let embeddedSig;

        //     // ตรวจสอบ Mime Type เพื่อเลือกฟังก์ชันที่ถูกต้อง
        //     if (data.SignatureMime === 'image/png') {embeddedSig = await pdfDoc.embedPng(sigBytes);} 
        //     else if (data.SignatureMime === 'image/jpeg' || data.SignatureMime === 'image/jpg') {embeddedSig = await pdfDoc.embedJpg(sigBytes);} 
        //     else {embeddedSig = await pdfDoc.embedPng(sigBytes);}
            
        //     if (userSignatureField) {userSignatureField.setImage(embeddedSig);}
        // } 
        // catch (error) {
        //     console.error("Signature Error:", error);
        //     fields.userName.setText(data.User || "");
        // }
    } 
    else {
        if (fields.userName) {fields.userName.setText(data.User || "");}
        // (Option) ล้างรูปที่ปุ่มถ้ามีค้างอยู่
        // sigField?.setImage(null); 
    }

    // Fill in Date
    handleDate(data.Date, fields.dayCreate, fields.monthCreate, fields.yearCreate);
    // const date = new Date(data.Date);
    // const result = date.toLocaleDateString('th-TH', {year: 'numeric',month: 'short',day: 'numeric',})
    // const splitDate = result.split(' ')
    // fields.dayCreate.setText(splitDate[0])
    // fields.monthCreate.setText(splitDate[1])
    // fields.yearCreate.setText(splitDate[2])

// Approver Section
    if (data.ApproveStatus) {
        console.log("Approve Status:", data.ApproveStatus);
        // Fill in Approver Field
        handleSignature(data.ApproveSignature, data.ApproveSignatureMime, approverSignatureField);
        // Fill in Date
        handleDate(data.ApproveDate, fields.dayApprove, fields.monthApprove, fields.yearApprove)
    }


    // Workaround to bind Thai font
    const rawUpdateFieldAppearances = form.updateFieldAppearances.bind(form);
    form.updateFieldAppearances = function () {
      return rawUpdateFieldAppearances(thaiFont);
    };

    // Serialize the PDFDocument to bytes (a Uint8Array)
    const pdfBytes = await pdfDoc.save()

    // Save form to global variable
    pdfFile = pdfBytes

    // Download the PDF
    download(pdfFile, fileName + ".pdf", "application/pdf");
}

function DownloadForm(data) {
    const currentDate = new Date();
    const isoDate = currentDate.toISOString().slice(0, 10);
    var fileName = prompt("กรุณาตั้งชื่อไฟล์", isoDate);
    if(fileName != null && fileName != ""){
        CreateForm(data, fileName);
    }
}