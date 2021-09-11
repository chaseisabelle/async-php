up:
	docker-compose up -d

logs:
	docker-compose logs -f --tail=100

stop:
	docker-compose stop

rm:
	docker-compose rm -f

ps:
	docker-compose ps

redo:
	make stop && make rm && make up && make ps && make curl

curl:
	curl 127.0.0.1:8080