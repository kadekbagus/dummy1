#!/bin/bash
#
# @author Rio Astamal <me@rioastamal.net>
# @desc Script to export Orbit database and its resources to a single archive
#       file.
#

MYPID=$$
TARGET_DIR=
MYSQLDUMP_PATH=
MYSQLDB=
MYSQLUSER=
MYSQLPASSWORD=
MYSQLPREFIX='orb_'
IGNORETABLES=
ORBIT_VERSION=
ORBIT_UPLOAD_DIR=

function help() {
    echo "Usage: $0 [OPTIONS]..."
    echo ""
    echo "OPTIONS:"
    echo " -o DIR                Output the result to DIR."
    echo " -m PATH               PATH to the mysqldump binary file."
    echo " -d DATABASE           Set the name of target database to DATABASE."
    echo " -u USERNAME           Set username of the MySQL to USERNAME."
    echo " -p PASSWORD           Set password of the MySQL to PASSWORD."
    echo " -e PREFIX             Set the table prefix of table name to PREFIX."
    echo " -k TABLE_1[,TABLE_N]  Skip the table named TABLE_1 and TABLE_N."
    echo " -r VERSION            Set this export to Orbit version VERSION."
    echo " -l UPLOAD_DIR         Location of the orbit uploads directory set to UPLOAD_DIR."
    echo " -h                    Print this screen and exit."
    echo ""
    echo "Example: "
    echo "    $0 -o /var/backup -m /usr/bin/mysqldump -d orbit_shop -u abc -p 123 -r 0.11"
    exit 1
}

# Check number of arguments
if [ $# -eq 0 ]; then
    help
fi

while getopts o:m:d:u:p:r:l:e:k:h ARG;
do
    case "${ARG}" in
        o)
            # Check if the dir is writeable
            TARGET_DIR=$OPTARG
            mkdir -p "${TARGET_DIR}" 2>/dev/null || {
                echo "Could not make directory ${TARGET_DIR}."
                exit 1
            }
        ;;

        m)
            # Check the existence of mysqldump binary file
            [ -f "${OPTARG}" ] || {
                echo "Could not find the path of mysqldump: ${OPTARG}."
                exit 2
            }

            MYSQLDUMP_PATH="${OPTARG}"
        ;;

        d)
            [ -z "${OPTARG}" ] && {
                echo "Database name is empty."
                exit 2
            }

            MYSQLDB=${OPTARG}
        ;;

        u)
            # MySQL username
            MYSQLUSER="${OPTARG}"
        ;;

        p)
            # MySQL Password
            MYSQLPASSWORD="${OPTARG}"
        ;;

        e)
            # MySQL Prefix table
            [ ! -z "${OPTARG}" ] && {
                MYSQLPREFIX=${OPTARG}
            }
        ;;

        k)
            # Skip MySQL Table
            [ ! -z "${OPTARG}" ] && {
                # Split the comma
                SKIP_TABLES=(${OPTARG//,/ })

                IGNORETABLES=""
                for TABLE in ${SKIP_TABLES[@]}
                do
                    IGNORETABLES=${IGNORETABLES}" --ignore-table=${MYSQLDB}.${MYSQLPREFIX}${TABLE}"
                done
            }
        ;;

        r)
           # Orbit version
           [ -z "${OPTARG}" ] && {
               echo "You need to specify orbit version."
               exit 3
           }

           ORBIT_VERSION=${OPTARG}
        ;;

        l)
            # Orbit uploads dir
            [ -d "${OPTARG}" ] || {
                echo "Could not find orbit uploads/ directory at ${OPTARG}."
                exit 3
            }

            ORBIT_UPLOAD_DIR="${OPTARG}"
        ;;

        *)
            help
        ;;
    esac
done

# Function to to dump orbit mysql data
function orbitdump_sql()
{
    local TARGET=$1
    local MYSQLDUMP=$2
    local MDB=$3
    local MUSER=$4
    local MPASS=$5
    local MPREFIX=$6

    # Run mysqldump to export the database structure
    echo "Dumping the structure of orbit database..."
    ${MYSQLDUMP} -u ${MUSER} -p${MPASS} --verbose ${MDB} --no-data > ${TARGET}-struct.sql || {
        echo "Failed dumping the database structure.";
        exit 4;
    }
    echo "Dumping database structure done."

    # Run mysqldump to export the data excluding user activities
    echo "Dumping orbit database data..."
    ${MYSQLDUMP} -u ${MUSER} -p${MPASS} --verbose --no-create-info ${IGNORETABLES} ${MDB} > ${TARGET}-data.sql || {
        echo "Failed dumping the database data.";
        exit 4;

    }
    echo "Dumping database data done."
}

# Create a directory named $TARGET_DIR/processing.$MYPID
# So we know that the export proces is not done yet.
TARGET_DIR_FULLPATH=$( readlink -f ${TARGET_DIR} )
ARCHIVE_NAME=orbit-dump-${ORBIT_VERSION}.$( date +%Y%m%dT%H%M%S )
PROCESSING_DIR_NAME=${TARGET_DIR_FULLPATH}/processing/${ARCHIVE_NAME}.${MYPID}
DONE_DIR_NAME="${TARGET_DIR_FULLPATH}/done/"

mkdir -p ${PROCESSING_DIR_NAME} 2>/dev/null || {
    echo "Failed creating 'processing' dirname: ${PROCESSING_DIR_NAME}";
    exit 5;
}
mkdir -p ${DONE_DIR_NAME} 2>/dev/null || {
    echo "Failed creating 'done' dirname:  ${DONE_DIR_NAME}"
}

orbitdump_sql ${PROCESSING_DIR_NAME}/orbit-${ORBIT_VERSION} \
              "${MYSQLDUMP_PATH}" "${MYSQLDB}" "${MYSQLUSER}" \
              "${MYSQLPASSWORD}" "${MYSQLPREFIX}"

cp -R ${ORBIT_UPLOAD_DIR}/ ${PROCESSING_DIR_NAME}/

echo ${ORBIT_VERSION} > ${PROCESSING_DIR_NAME}/orbit-version.txt

# Create tar archive
cd ${PROCESSING_DIR_NAME} && \
tar -zcvf ${ARCHIVE_NAME}.tar.gz \
    orbit-version.txt orbit-${ORBIT_VERSION}-struct.sql \
    orbit-${ORBIT_VERSION}-data.sql uploads

# If we goes here then the process is done. We need to move the archive to the
# done directory.
mv ${PROCESSING_DIR_NAME}/${ARCHIVE_NAME}.tar.gz ${DONE_DIR_NAME}/${ARCHIVE_NAME}.tar.gz
rm -rf ${PROCESSING_DIR_NAME}

echo "Export is done."
exit 0
