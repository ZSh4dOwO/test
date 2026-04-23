function getCardById(id,cardList){
    for(const card of cardList){
        if(card.id == id){
            return card;
        }
    }
}

function getRandomInt(max) {
    return Math.floor(Math.random() * max);
  }

function setTemplate(idTemplate,idContainer,cList){
    let template = document.querySelector(idTemplate);
    if(template == null){
        return
    }

    for(const card of cList){
        let clone = document.importNode(template.content, true);

        newContent= clone.firstElementChild.innerHTML
                    .replace(/{{family_src}}/g, card.family_src)
                    .replace(/{{family_name}}/g, card.family_name)
                    .replace(/{{img_src}}/g, card.img_src)
                    .replace(/{{name}}/g, card.name)
                    .replace(/{{description}}/g, card.description)
                    .replace(/{{hp}}/g, card.hp)
                    .replace(/{{energy}}/g, card.energy)
                    .replace(/{{attack}}/g, card.attack)
                    .replace(/{{defense}}/g, card.defense)
                    .replace(/{{price}}/g, card.price)
                    .replace(/{{onProcessParam}}/g,card.id);

        clone.firstElementChild.innerHTML= newContent;

        let cardContainer= document.querySelector(idContainer);
        cardContainer.appendChild(clone);
    }
}


function setTemplateRoom(idTemplate,idContainer,rList){
    let template = document.querySelector(idTemplate);
    if(template == null){
        return
    }

    for(const room of rList){
        let clone = document.importNode(template.content, true);

        newContent= clone.firstElementChild.innerHTML
                    .replace(/{{room_name}}/g, room.room_name)
                    .replace(/{{user_id}}/g, room.user_id)
                    .replace(/{{bet}}/g, room.bet)
                    .replace(/{{room_id}}/g, room.id);

        clone.firstElementChild.innerHTML= newContent;

        let roomContainer= document.querySelector(idContainer);
        roomContainer.appendChild(clone);
    }
}