SERVER := "olivierb@toto.o2switch.net"
DOMAIN := "api.youturn.10torsions.com"

.PHONY: install deploy

deploy:
	ssh -A $(SERVER) 'cd $(DOMAIN) && git pull origin main && make install'

install: vendor/autoload.php
	php bin/console doctrine:migrations:migrate -n
	composer dump-env prod
	php bin/console cache:clear

vendor/autoload.php: composer.lock composer.json
	composer install --no-dev --optimize-autoloader
	touch vendor/autoload.php
