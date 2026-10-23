import axios from 'axios';

export const http = axios.create({
    baseURL: '/api/v1',
    headers:{
        Accept : 'application/json',
        'Content-Type':'application/json'
    }
})