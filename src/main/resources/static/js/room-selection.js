
let user ={"username":"John","wallet":5000}
let roomList = [
        {
            id:1,
            room_name:"Room 1",
            user_id: 25,
            bet: 150,
        },
        {
            id:2,
            room_name:"Room 2",
            user_id: 45,
            bet: 1000,
        },
        {
            id:3,
            room_name:"Room 3",
            user_id: 14,
            bet: 300,
        },
        {
            id:4,
            room_name:"Room 4",
            user_id: 2,
            bet: 150,
        },
        {
            id:5,
            room_name:"Room 5",
            user_id: 21,
            bet: 10000,
        },
        {
            id:6,
            room_name:"Room 6",
            user_id: 5,
            bet: 50
        }

    ]

function onProcess(id){
    let roomName = document.getElementsByName('room-name');
    let betName = document.getElementsByName('bet');
    

    data = {"roomName":roomName[0].value, "bet":betName[0].value}
    console.log(data);
}

function onProcessGame(obj){
    let room_id =obj.firstElementChild.innerHTML;
    console.log(room_id);
    document.location.href="/cardList-select-card.html?room_id="+room_id; 
}


function setUserInfo(){
    document.getElementById("userNameId").innerHTML= user.username;
    document.getElementById("walletId").innerHTML= user.wallet;
}

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);
const userCardId = urlParams.get('id');

setUserInfo()
setTemplateRoom("#roomlist","#roomContent",roomList)







