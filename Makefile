all:
		docker compose -f ./docker-compose.yml up -d --build

clean:
		docker compose -f ./docker-compose.yml down

fclean:
		docker compose -f ./docker-compose.yml down -v
		rm -rf ./php_apache/www/uploads/*

re:
		make fclean
		make all

up: 
		docker compose -f ./docker-compose.yml up -d --build

down: 
		docker compose -f ./docker-compose.yml down

populate:
		docker exec php_apache bash -c "php /usr/local/bin/script/populate.php && mkdir -p /var/www/html/uploads/populate && mkdir -p /var/www/html/uploads/1 && cp -R /usr/local/bin/script/images/* /var/www/html/uploads/populate/ && cp -R /usr/local/bin/script/images/* /var/www/html/uploads/1 && chmod 777 /var/www/html/uploads/populate && chmod 777 /var/www/html/uploads/1"
# 		users:
# 			2 to 500
# 			user@2.com/user2:user2

prune:
		make clean
		docker system prune

.PHONY: all clean fclean re up down populate prune
