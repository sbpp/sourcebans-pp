<?php

class Admin
{
    public function add($name, $steam, $password, $email, $webGroup, $webFlags, $srvGroup, $srvFlags, $immunity, $srvPassword)
    {
        $pwMinLength = Flight::config()->get('config.password.minlength');
        if (empty($password)) {
            throw new Exception('Password must not be empty.');
        } elseif (strlen($password) < $pwMinLength) {
            throw new Exception('Password must be at least '.$pwMinLength.' characters long.');
        }

        Flight::db()->query(
            "INSERT INTO `:prefix_admins`
            (user, authid, password, gid, email, extraflags, immunity, srv_group, srv_flags, srv_password)
            VALUES
            (:user, :authid, :password, :gid, :email, :extraflags, :immunity, :srv_group, :srv_flags, :srv_password)"
        );

        Flight::db()->bind(':user', $name);
        Flight::db()->bind(':authid', $steam);
        Flight::db()->bind(':password', password_hash($password, PASSWORD_BCRYPT));
        Flight::db()->bind(':gid', $webGroup);
        Flight::db()->bind(':email', $email);
        Flight::db()->bind(':extraflags', $webFlags);
        Flight::db()->bind(':immunity', $immunity);
        Flight::db()->bind(':srv_group', $srvGroup);
        Flight::db()->bind(':srv_flags', $srvFlags);
        Flight::db()->bind(':srv_password', $srvPassword);

        return (Flight::db()->execute()) ? Flight::db()->lastInsertId() : -1;
    }
}
