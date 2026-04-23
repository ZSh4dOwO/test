function process(elt){
    
    let firstName = document.getElementsByName('first-name');
    let lastName = document.getElementsByName('last-name');
    let password = document.getElementsByName('password');
    let rePassword = document.getElementsByName('re-password');

    data = {"firstName":firstName[0].value, "lastName":lastName[0].value, "password":password[0].value}
    console.log(data);
}

function check(input){
    console.log("checked");
}