Общий концепт (REST API)
=============

Примеры запросов и ресурсов:

GET task/<id> - получить полное описание задачи с айдишником id.  
GET task/<id>/comment - получить все комментарии для задачи id.  
POST project/<id>/task - добавить новую задачу в проект с айдишником id.

Ошибки возвращаются через HTTP Response Codes. Стандартные коды:

200 - ОК  
400 - Bad Request (если даны неправельные параметры запроса)  
401 - Unauthorized (если не прошли авторизацию)  
403 - Forbidden (если нет нужных разрешений у юзера)  
405 - Invalid Method Call (возвращается автоматически если не реализован метод)  
500 - Internal Server Error (возвращается автоматически, когда есть ошибка PHP)  

GET - запрос на получение ресурса, обычно параметры передаются в $_GET, возвращает:  
200 - OK и описание ресурса как JSON в ответе.  
404 - Not Found

POST - запрос для создания нового ресурса. Возвращает:  
201 - OK (Created) и обычно JSON с айдишником созданного ресурса.  

PUT - запрос на изменение существующего ресурса. Все новые данные передаются как JSON в теле запроса. Возвращает:  
200 - OK

DELETE - запрос на удаление ресурса. Используется редко, потому что обычно ресурсы только отмечаются как удаленные, чтобы не нарушать целостности базы данных.  
204 - (OK) No Content  
404 - Not Found  


Юзеры
=============

**GET user**
Список пользователей
<pre>
{
    from: 0,
    count: 24,
    items: [] // параметры, которые необходимо вернуть. Если отсутствуют - использовать дефолтный набор
}
</pre>

**GET user/_id**
Данные пользователя

**GET user/_id/clientSettings**
Возвращает настройки клиента пользователя

**GET user/_id/vars**
Возвращает доп. информацию о пользователе
_устарело_

**GET user/_id/avatar**
Возвращает ссылку на аватару юзера, чтобы скачать.  
<pre>
{
    “url” : “http://office.divogames.ru/todo/avatars/1.jpg”,
}
</pre>
_устарело_  

**POST user**  
_access: administer, people.management_  
Создание пользователя  
<pre>
Request: 
{
  username: "",
  email: ""
}
</pre>

**PUT user/_id/vars**
_access: administer, people.management, owner_  
Изменение доп. информации о  пользователе  
_устарело_  

**PUT user/_id/clientSettings**  
_access: owner_  
Изменение настроек клиента пользователя  

**PUT user/_id/password**
_access: people.management, user (self)_  
Изменение пароля. Админам менять пароли могут только они сами или другие админы.
<pre>
Request: 
{
  password: md5(text)
}
</pre>
_устарело_  


**PUT user/_id**  
_access: administer, people.management_  
Изменение данных пользователя  
people.management не может устанавливать роль “admin”, нельзя менять самому себе
 
Вообще people.management - читерский доступ. Можно нахоботить себе любой доступ кроме админского через фейковый аккаунт, например.
Поля для менеджера:
deleted
username
role
def_role
group
firstname
lastname

**PUT user/_id/projectrole**  
_access: administer, people.management_  
Установить проектную роль.  
Если роль пустая - запись удаляется  
<pre>
Request: {
  “project”:
  “role”:
}
</pre>

**POST user/_id/avatar?filename&size**  
_access: owner_  
Загрузить аватарку для юзера.  
filename: исходное имя картинки (по нему можно формат определить)  
size: размер данных в байтах.  

Body: Бинарные данные картинки с аватаркой.