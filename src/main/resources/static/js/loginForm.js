function process(elt){
    
    let login = document.getElementsByName('login');
    let password = document.getElementsByName('password');
    

    data = {"login":login[0].value, "password":password[0].value}
    console.log(data);
}
